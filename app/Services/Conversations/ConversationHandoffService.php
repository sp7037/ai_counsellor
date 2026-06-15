<?php

namespace App\Services\Conversations;

use App\Enums\Audit\AuditAction;
use App\Enums\Conversations\ConversationActivityType;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\HandoffRecordStatus;
use App\Enums\Conversations\MessageRole;
use App\Enums\Leads\LeadSource;
use App\Enums\Tenancy\TenantRole;
use App\Models\Conversation;
use App\Models\ConversationHandoff;
use App\Models\Lead;
use App\Models\Message;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WidgetSession;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\WidgetEntitlementService;
use App\Services\Leads\LeadAssignmentService;
use App\Services\Leads\LeadCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ConversationHandoffService
{
    public function __construct(
        private readonly ConversationTransitionService $transitions,
        private readonly ConversationActivityLogger $activity,
        private readonly ConversationNotificationService $notifications,
        private readonly LeadCreationService $leadCreation,
        private readonly LeadAssignmentService $leadAssignment,
        private readonly AuditLogger $audit,
        private readonly WidgetEntitlementService $widgetEntitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $leadInput
     */
    public function requestFromWidget(
        WidgetSession $session,
        string $handoffRequestUuid,
        array $leadInput = [],
    ): array {
        $conversation = $session->conversation;

        if ($conversation->handoff_request_uuid === $handoffRequestUuid) {
            $conversation->loadMissing('lead');

            return [
                'conversation' => $conversation,
                'acknowledgement' => $this->findHandoffAcknowledgement($conversation, $handoffRequestUuid),
                'lead' => $conversation->lead,
                'replay' => true,
            ];
        }

        if ($conversation->mode === ConversationMode::Closed) {
            throw ValidationException::withMessages(['handoff' => 'Conversation is closed.']);
        }

        if (in_array($conversation->mode, [ConversationMode::Human, ConversationMode::HandoffRequested], true)) {
            $conversation->loadMissing('lead');

            return [
                'conversation' => $conversation,
                'acknowledgement' => $this->findHandoffAcknowledgement($conversation, $handoffRequestUuid),
                'lead' => $conversation->lead,
                'replay' => true,
            ];
        }

        $handoffEntitlement = $this->widgetEntitlements->canRequestHandoff($session->tenant);

        if (! $handoffEntitlement->isAllowed()) {
            throw new AccessDeniedHttpException(
                config('subscriptions.widget_unavailable_message'),
            );
        }

        $result = DB::transaction(function () use ($conversation, $handoffRequestUuid, $leadInput): array {
            $lead = $this->ensureLead($conversation, $leadInput);

            $conversation->update([
                'handoff_request_uuid' => $handoffRequestUuid,
                'target_counsellor_id' => $lead?->assigned_to,
            ]);

            $this->transitions->transition(
                $conversation->fresh(),
                ConversationMode::HandoffRequested,
                metadata: ['handoff_request_uuid' => $handoffRequestUuid],
            );

            $ack = $this->createHandoffAcknowledgement($conversation->fresh(), $handoffRequestUuid);

            return [
                'conversation' => $conversation->fresh(),
                'acknowledgement' => $ack,
                'lead' => $lead,
                'replay' => false,
            ];
        });

        $this->notifyHandoffRequested($result['conversation'], $result['lead'] ?? null);

        return $result;
    }

    public function claim(Conversation $conversation, User $counsellor, ?User $assignedBy = null, ?string $note = null): Conversation
    {
        if ($counsellor->tenantRoleFor($conversation->tenant) !== TenantRole::Staff) {
            throw ValidationException::withMessages(['counsellor' => 'Only active counsellors can claim conversations.']);
        }

        if (! $counsellor->hasActiveMembership($conversation->tenant)) {
            throw ValidationException::withMessages(['counsellor' => 'Counsellor is not active in this tenant.']);
        }

        return DB::transaction(function () use ($conversation, $counsellor, $assignedBy, $note): Conversation {
            $locked = Conversation::withoutGlobalScopes()
                ->where('tenant_id', $conversation->tenant_id)
                ->where('id', $conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->mode === ConversationMode::Closed) {
                throw ValidationException::withMessages(['conversation' => 'Conversation is closed.']);
            }

            if (! in_array($locked->mode, [ConversationMode::HandoffRequested, ConversationMode::Human], true)) {
                throw ValidationException::withMessages(['conversation' => 'Conversation is not waiting for human support.']);
            }

            if ($locked->mode === ConversationMode::Human && $locked->human_owner_id !== null && $locked->human_owner_id !== $counsellor->id) {
                throw ValidationException::withMessages(['conversation' => 'Another counsellor already owns this conversation.']);
            }

            $this->releaseCurrentHandoff($locked);

            ConversationHandoff::query()->create([
                'tenant_id' => $locked->tenant_id,
                'conversation_id' => $locked->id,
                'counsellor_id' => $counsellor->id,
                'assigned_by' => $assignedBy?->id,
                'status' => HandoffRecordStatus::Active,
                'is_current' => true,
                'note' => $note,
                'claimed_at' => now(),
            ]);

            $locked->update([
                'human_owner_id' => $counsellor->id,
                'target_counsellor_id' => $counsellor->id,
            ]);

            $this->transitions->transition($locked->fresh(), ConversationMode::Human, $counsellor);

            $this->activity->log(
                $locked->fresh(),
                ConversationActivityType::HandoffClaimed,
                $counsellor,
                array_filter(['note' => $note]),
            );

            $this->maybeAssignLead($locked->fresh(), $counsellor, $assignedBy ?? $counsellor);

            $this->notifications->handoffClaimed($locked->fresh(), $counsellor);

            if ($assignedBy !== null && $assignedBy->id !== $counsellor->id) {
                $this->audit->log(
                    AuditAction::ConversationReassigned,
                    $locked,
                    $locked->tenant_id,
                    ['counsellor_id' => $counsellor->id],
                    $assignedBy,
                );
            }

            return $locked->fresh();
        });
    }

    public function release(Conversation $conversation, User $actor, ?string $reason = null, bool $resumeAi = true): Conversation
    {
        return DB::transaction(function () use ($conversation, $actor, $reason, $resumeAi): Conversation {
            $locked = Conversation::withoutGlobalScopes()
                ->where('tenant_id', $conversation->tenant_id)
                ->where('id', $conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->releaseCurrentHandoff($locked);

            $targetMode = $resumeAi ? ConversationMode::Ai : ConversationMode::HandoffRequested;

            $locked->update(['human_owner_id' => null]);

            $this->transitions->transition($locked->fresh(), $targetMode, $actor, $reason);

            $this->activity->log(
                $locked->fresh(),
                ConversationActivityType::OwnershipReleased,
                $actor,
                array_filter(['reason' => $reason, 'resume_ai' => $resumeAi]),
            );

            return $locked->fresh();
        });
    }

    public function assignCounsellor(Conversation $conversation, User $counsellor, User $admin, ?string $note = null): Conversation
    {
        if ($counsellor->tenantRoleFor($conversation->tenant) !== TenantRole::Staff) {
            throw ValidationException::withMessages(['counsellor' => 'Target user must be an active counsellor.']);
        }

        $conversation->update(['target_counsellor_id' => $counsellor->id]);

        $this->activity->log(
            $conversation->fresh(),
            ConversationActivityType::CounsellorAssigned,
            $admin,
            array_filter(['counsellor_id' => $counsellor->id, 'note' => $note]),
        );

        $this->notifications->conversationAssigned($conversation->fresh(), $counsellor, $admin);

        $this->audit->log(
            AuditAction::ConversationReassigned,
            $conversation,
            $conversation->tenant_id,
            ['counsellor_id' => $counsellor->id],
            $admin,
        );

        return $conversation->fresh();
    }

    public function close(Conversation $conversation, User $actor, ?string $reason = null): Conversation
    {
        return DB::transaction(function () use ($conversation, $actor, $reason): Conversation {
            $this->releaseCurrentHandoff($conversation);
            $conversation->update(['human_owner_id' => null]);

            $closed = $this->transitions->transition($conversation->fresh(), ConversationMode::Closed, $actor, $reason);

            $this->audit->log(
                AuditAction::ConversationClosed,
                $closed,
                $closed->tenant_id,
                array_filter(['reason' => $reason]),
                $actor,
            );

            return $closed;
        });
    }

    public function reopen(Conversation $conversation, User $actor, ConversationMode $mode, ?string $reason = null): Conversation
    {
        if (! in_array($mode, [ConversationMode::Ai, ConversationMode::HandoffRequested], true)) {
            throw ValidationException::withMessages(['mode' => 'Reopen target mode must be ai or handoff_requested.']);
        }

        $reopened = $this->transitions->transition($conversation, $mode, $actor, $reason);

        $this->audit->log(
            AuditAction::ConversationReopened,
            $reopened,
            $reopened->tenant_id,
            ['mode' => $mode->value, 'reason' => $reason],
            $actor,
        );

        return $reopened;
    }

    private function releaseCurrentHandoff(Conversation $conversation): void
    {
        ConversationHandoff::query()
            ->where('conversation_id', $conversation->id)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'status' => HandoffRecordStatus::Released->value,
                'released_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $leadInput
     */
    private function ensureLead(Conversation $conversation, array $leadInput): ?Lead
    {
        if ($conversation->lead_id !== null) {
            return $conversation->lead;
        }

        $visitor = $conversation->visitor;

        return $this->leadCreation->create(
            $conversation->tenant,
            LeadSource::WidgetConversation,
            array_merge([
                'conversation_id' => $conversation->id,
                'full_name' => $leadInput['full_name'] ?? 'Visitor',
                'email' => $leadInput['email'] ?? null,
                'enquiry_summary' => $leadInput['enquiry_summary'] ?? 'Visitor requested human support.',
                'requested_human_contact' => true,
            ], $leadInput),
            captureEventUuid: null,
            sourceReference: 'handoff:'.$conversation->uuid,
        );
    }

    private function createHandoffAcknowledgement(Conversation $conversation, string $handoffRequestUuid): Message
    {
        $existing = $conversation->messages()
            ->where('role', MessageRole::System->value)
            ->where('metadata->handoff_request_uuid', $handoffRequestUuid)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $settings = TenantSettings::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->first();

        $body = trim((string) ($settings?->human_transfer_message ?? ''));
        $body = $body !== '' ? $body : config('conversations.handoff_acknowledgement');

        return Message::query()->create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'role' => MessageRole::System,
            'body' => $body,
            'metadata' => ['handoff_request_uuid' => $handoffRequestUuid, 'type' => 'handoff_ack'],
        ]);
    }

    private function findHandoffAcknowledgement(Conversation $conversation, string $handoffRequestUuid): ?Message
    {
        return $conversation->messages()
            ->where('role', MessageRole::System->value)
            ->where('metadata->handoff_request_uuid', $handoffRequestUuid)
            ->first();
    }

    private function notifyHandoffRequested(Conversation $conversation, ?Lead $lead): void
    {
        $recipientId = $lead?->assigned_to ?? $conversation->target_counsellor_id;

        if ($recipientId === null) {
            $admins = $conversation->tenant->memberships()
                ->whereIn('role', [TenantRole::Owner->value, TenantRole::Admin->value])
                ->where('status', 'active')
                ->pluck('user_id');

            foreach ($admins as $adminId) {
                $this->notifications->handoffRequested($conversation, (int) $adminId);
            }

            return;
        }

        $this->notifications->handoffRequested($conversation, (int) $recipientId);
    }

    private function maybeAssignLead(Conversation $conversation, User $counsellor, User $actor): void
    {
        $conversation->loadMissing('lead');

        if ($conversation->lead === null || $conversation->lead->assigned_to !== null) {
            return;
        }

        $this->leadAssignment->assign($conversation->lead, $counsellor, $actor);
    }
}
