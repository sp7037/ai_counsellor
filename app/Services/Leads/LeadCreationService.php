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
        private readonly LeadNameGuard $nameGuard,
        private readonly LeadIdentityResolver $identity,
        private readonly LeadMetadataUpdateService $metadataUpdate,
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

            $conversation = isset($input['conversation_id'])
                ? Conversation::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->find($input['conversation_id'])
                : null;

            $matched = $this->identity->resolve(
                $tenant,
                $input['mobile'] ?? null,
                $input['email'] ?? null,
                $conversation,
            );

            if ($matched !== null && $this->hasIdentitySignal($input)) {
                return $this->mergeMatchedLead(
                    $matched,
                    $source,
                    $input,
                    $actor,
                    $conversation,
                    $this->resolveMatchType($input),
                );
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
                'full_name' => $this->resolveFullName($input['full_name'] ?? null),
                'mobile' => $this->identity->normalizeMobile($input['mobile'] ?? null),
                'email' => $this->identity->normalizeEmail($input['email'] ?? null),
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

    /**
     * @param  array<string, mixed>  $input
     */
    private function mergeMatchedLead(
        Lead $lead,
        LeadSource $source,
        array $input,
        ?User $actor,
        ?Conversation $conversation,
        ?string $matchType,
    ): Lead {
        if ($conversation !== null) {
            $this->identity->linkConversation($conversation, $lead);
        }

        return $this->metadataUpdate->mergeExtractedData($lead, $this->inputToExtracted($input), [
            'source' => $this->sourceKey($source),
            'log_identity_match' => true,
            'conversation_id' => $conversation?->id,
            'match_type' => $matchType,
            'enquiry_summary_append' => $input['enquiry_summary'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function inputToExtracted(array $input): array
    {
        return array_filter([
            'full_name' => $input['full_name'] ?? null,
            'mobile' => $input['mobile'] ?? null,
            'email' => $input['email'] ?? null,
            'country' => $input['country'] ?? null,
            'location' => $input['location'] ?? null,
            'programme_interest' => $input['programme_interest'] ?? null,
            'service_interest' => $input['service_interest'] ?? null,
            'metadata' => $input['metadata'] ?? null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasIdentitySignal(array $input): bool
    {
        return $this->identity->normalizeMobile($input['mobile'] ?? null) !== null
            || $this->identity->normalizeEmail($input['email'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveMatchType(array $input): ?string
    {
        if ($this->identity->normalizeMobile($input['mobile'] ?? null) !== null) {
            return 'mobile';
        }

        if ($this->identity->normalizeEmail($input['email'] ?? null) !== null) {
            return 'email';
        }

        return null;
    }

    private function sourceKey(LeadSource $source): string
    {
        return match ($source) {
            LeadSource::WidgetConversation => 'widget_chat',
            LeadSource::WidgetForm => 'widget_form',
            LeadSource::WhatsApp => 'whatsapp',
            LeadSource::OfflineIntake => 'offline_intake',
            default => $source->value,
        };
    }

    private function resolveFullName(?string $candidate): string
    {
        if ($this->nameGuard->isValidPersonName($candidate)) {
            return trim((string) $candidate);
        }

        $candidate = trim((string) ($candidate ?? ''));

        if ($candidate === '') {
            return 'Unknown';
        }

        if (in_array(strtolower($candidate), ['visitor', 'unknown'], true)) {
            return ucfirst(strtolower($candidate));
        }

        return 'Visitor';
    }
}
