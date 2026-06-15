<?php

namespace App\Services\Leads;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\PlanFeature;
use App\Enums\Leads\LeadActivityType;
use App\Enums\Leads\LeadSource;
use App\Enums\Leads\LeadStage;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\EntitlementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadCreationService
{
    public function __construct(
        private readonly LeadQualificationEngine $qualification,
        private readonly LeadActivityLogger $activity,
        private readonly AuditLogger $audit,
        private readonly EntitlementResolver $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(
        Tenant $tenant,
        LeadSource $source,
        array $input,
        ?User $actor = null,
        ?string $captureEventUuid = null,
        ?string $sourceReference = null,
    ): Lead {
        return DB::transaction(function () use ($tenant, $source, $input, $actor, $captureEventUuid, $sourceReference): Lead {
            if ($captureEventUuid !== null) {
                $existing = Lead::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('capture_event_uuid', $captureEventUuid)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            if ($sourceReference !== null) {
                $existing = Lead::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('source', $source->value)
                    ->where('source_reference', $sourceReference)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            try {
                $this->entitlements->assertAllowed($tenant, PlanFeature::LeadManagement);
            } catch (EntitlementDeniedException $exception) {
                throw ValidationException::withMessages(['lead' => $exception->getMessage()]);
            }

            $lead = new Lead([
                'tenant_id' => $tenant->id,
                'conversation_id' => $input['conversation_id'] ?? null,
                'source' => $source,
                'source_reference' => $sourceReference,
                'capture_event_uuid' => $captureEventUuid,
                'created_by' => $actor?->id,
                'full_name' => trim((string) ($input['full_name'] ?? 'Unknown')),
                'mobile' => $this->normalizeMobile($input['mobile'] ?? null),
                'email' => isset($input['email']) ? strtolower(trim((string) $input['email'])) : null,
                'preferred_contact_method' => $input['preferred_contact_method'] ?? null,
                'location' => $input['location'] ?? null,
                'state' => $input['state'] ?? null,
                'country' => $input['country'] ?? null,
                'service_interest' => $input['service_interest'] ?? null,
                'programme_interest' => $input['programme_interest'] ?? null,
                'enquiry_summary' => $input['enquiry_summary'] ?? null,
                'stage' => LeadStage::Unassigned,
                'metadata' => $input['metadata'] ?? null,
            ]);

            $this->qualification->applyToLead($lead, $input);
            $lead->save();

            if ($lead->conversation_id !== null) {
                Conversation::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('id', $lead->conversation_id)
                    ->update(['lead_id' => $lead->id]);
            }

            $this->activity->log($lead, LeadActivityType::Created, $actor, [
                'source' => $source->value,
            ]);

            $this->audit->log(
                AuditAction::LeadCreated,
                $lead,
                $tenant->id,
                ['public_reference' => $lead->public_reference, 'source' => $source->value],
                $actor,
            );

            return $lead->fresh();
        });
    }

    public function fromConversation(Conversation $conversation, array $input, User $actor): Lead
    {
        if ($conversation->lead_id !== null) {
            throw ValidationException::withMessages([
                'conversation' => 'This conversation already has a linked lead.',
            ]);
        }

        return $this->create(
            $conversation->tenant,
            LeadSource::ConversationConversion,
            array_merge($input, ['conversation_id' => $conversation->id]),
            $actor,
            sourceReference: 'conversation:'.$conversation->uuid,
        );
    }

    private function normalizeMobile(?string $mobile): ?string
    {
        if ($mobile === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        return $digits !== '' ? $digits : null;
    }
}
