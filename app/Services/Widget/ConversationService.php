<?php

namespace App\Services\Widget;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\UsageMetric;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantSettings;
use App\Models\WidgetSession;
use App\Services\AI\AiConversationOrchestrator;
use App\Services\AI\AiIdempotencyCoordinator;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\UsageTrackingService;
use App\Services\Billing\WidgetEntitlementService;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Leads\ChatLeadExtractionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        private readonly AiConversationOrchestrator $orchestrator,
        private readonly KnowledgeRetrievalContract $knowledgeRetrieval,
        private readonly AiIdempotencyCoordinator $idempotency,
        private readonly ConversationMessageService $conversationMessages,
        private readonly WidgetEntitlementService $widgetEntitlements,
        private readonly EntitlementResolver $entitlements,
        private readonly UsageTrackingService $usage,
        private readonly ChatLeadExtractionService $leadExtraction,
    ) {}

    public function addVisitorMessage(WidgetSession $session, string $body, ?string $requestId = null): array
    {
        $conversation = $session->conversation;

        if ($conversation->status !== ConversationStatus::Open || $conversation->mode === ConversationMode::Closed) {
            throw ValidationException::withMessages(['body' => 'Conversation is closed.']);
        }

        if (! $conversation->mode->allowsAiResponse()) {
            $humanResult = $this->conversationMessages->addVisitorMessageInHumanMode($session, $body, $requestId);

            return [
                'visitor_message' => $humanResult['visitor_message'],
                'reply' => $humanResult['reply'] ?? $this->waitingReply($conversation),
                'mode' => $humanResult['mode'],
            ];
        }

        $body = trim(strip_tags($body));

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        if (strlen($body) > config('widget.max_message_length', 4000)) {
            throw ValidationException::withMessages(['body' => 'Message is too long.']);
        }

        $resolved = $this->idempotency->resolveVisitorMessage(
            tenant: $session->tenant,
            conversation: $conversation,
            body: $body,
            clientRequestId: $requestId,
        );

        if ($resolved['replay'] !== null) {
            return [
                'visitor_message' => $resolved['visitor_message'],
                'reply' => $resolved['replay']['reply'],
                'mode' => $conversation->mode->value,
            ];
        }

        $aiEntitlement = $this->widgetEntitlements->canUseAi($session->tenant);

        if (! $aiEntitlement->isAllowed()) {
            $visitorMessage = $resolved['visitor_message'];

            return [
                'visitor_message' => $visitorMessage,
                'reply' => null,
                'mode' => $conversation->mode->value,
            ];
        }

        $subscription = $this->entitlements->subscriptionFor($session->tenant);
        $limit = $this->entitlements->featureLimit($session->tenant, PlanFeature::AiResponses);
        $reserved = $subscription !== null && $this->usage->reserve(
            $session->tenant,
            UsageMetric::AiRuns,
            $subscription,
            1,
            $limit,
        );

        if ($subscription !== null && ! $reserved) {
            return [
                'visitor_message' => $resolved['visitor_message'],
                'reply' => null,
                'mode' => $conversation->mode->value,
            ];
        }

        $this->leadExtraction->processMessage($session->tenant, $conversation->fresh(), $body);
        $conversation->refresh()->loadMissing('lead');

        $knowledge = $this->knowledgeRetrieval->searchPublished(
            $session->tenant,
            $body,
            (int) config('ai.max_knowledge_items', 6),
        );

        $aiResult = $this->orchestrator->respond(
            tenant: $session->tenant,
            conversation: $conversation,
            triggeringMessage: $resolved['visitor_message'],
            visitorMessage: $body,
            knowledge: $knowledge,
            requestUuid: $resolved['request_uuid'],
        );

        if ($subscription !== null) {
            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                $this->usage->confirmReservation($session->tenant, UsageMetric::AiRuns, $subscription);
            } else {
                $this->usage->releaseReservation($session->tenant, UsageMetric::AiRuns, $subscription);
            }
        }

        $reply = DB::transaction(function () use ($session, $conversation, $aiResult): Message {
            $conversation->update([
                'last_message_at' => now(),
                'last_visitor_message_at' => now(),
            ]);

            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                if ($aiResult['run']->message !== null) {
                    return $aiResult['run']->message;
                }

                $finalized = app(AiIdempotencyCoordinator::class)->finalizeSuccess(
                    $aiResult['run'],
                    $aiResult['content'],
                );

                return $finalized['assistant'];
            }

            return Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::System->value,
                'body' => $this->safeFallbackMessage($session->tenant_id),
            ]);
        });

        return [
            'visitor_message' => $resolved['visitor_message'],
            'reply' => $reply,
            'mode' => $conversation->fresh()->mode->value,
        ];
    }

    public function submitOfflineIntake(
        WidgetSession $session,
        string $message,
        ?string $name = null,
        ?string $email = null,
    ): Message {
        $message = trim($message);

        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Message cannot be empty.']);
        }

        if (strlen($message) > config('widget.max_offline_message_length', 2000)) {
            throw ValidationException::withMessages(['message' => 'Message is too long.']);
        }

        return DB::transaction(function () use ($session, $message, $name, $email): Message {
            $conversation = $session->conversation;

            $intake = Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::OfflineIntake->value,
                'body' => $message,
                'metadata' => array_filter([
                    'name' => $name,
                    'email' => $email,
                ]),
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $intake;
        });
    }

    public function listMessages(Conversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Message $message) => $message->role->isPublicWidgetVisible())
            ->map(fn (Message $message) => app(ConversationMessageService::class)->serializePublicMessage($message))
            ->all();
    }

    private function safeFallbackMessage(int $tenantId): string
    {
        $settings = TenantSettings::query()->where('tenant_id', $tenantId)->first();
        $handover = trim((string) ($settings?->human_transfer_message ?? ''));

        if ($handover !== '') {
            return Str::limit($handover, (int) config('ai.max_output_chars', 3000), '');
        }

        return 'Our assistant is temporarily unavailable. Please try again shortly or contact our team.';
    }

    private function waitingReply(Conversation $conversation): ?Message
    {
        if ($conversation->mode !== ConversationMode::HandoffRequested) {
            return null;
        }

        return $conversation->messages()
            ->where('role', MessageRole::System->value)
            ->where('metadata->type', 'handoff_ack')
            ->latest('id')
            ->first();
    }
}
