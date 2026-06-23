<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadActivityType;
use App\Models\Lead;

class LeadMetadataUpdateService
{
    public function __construct(
        private readonly LeadQualificationEngine $qualification,
        private readonly LeadNameGuard $nameGuard,
        private readonly LeadIdentityResolver $identity,
        private readonly LeadActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $options
     */
    public function mergeExtractedData(Lead $lead, array $extracted, array $options = []): Lead
    {
        $updates = [];
        $identityMatched = false;

        if (! empty($extracted['full_name']) && $this->nameGuard->shouldReplaceExistingName($lead->full_name, (string) $extracted['full_name'])) {
            $updates['full_name'] = trim((string) $extracted['full_name']);
        }

        $incomingMobile = $this->identity->normalizeMobile($extracted['mobile'] ?? null);

        if ($incomingMobile !== null && blank($lead->mobile)) {
            $updates['mobile'] = $incomingMobile;
        }

        $incomingEmail = $this->identity->normalizeEmail($extracted['email'] ?? null);

        if ($incomingEmail !== null && blank($lead->email)) {
            $updates['email'] = $incomingEmail;
        }

        if (! empty($extracted['country']) && blank($lead->country)) {
            $updates['country'] = trim((string) $extracted['country']);
        }

        if (! empty($extracted['location']) && blank($lead->location)) {
            $updates['location'] = trim((string) $extracted['location']);
        }

        if (! empty($extracted['programme_interest']) && blank($lead->programme_interest)) {
            $updates['programme_interest'] = trim((string) $extracted['programme_interest']);
        }

        if (! empty($extracted['service_interest']) && blank($lead->service_interest)) {
            $updates['service_interest'] = trim((string) $extracted['service_interest']);
        }

        $metadata = is_array($lead->metadata) ? $lead->metadata : [];

        foreach ((array) ($extracted['metadata'] ?? []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'country_preference' || ! array_key_exists($key, $metadata) || blank($metadata[$key])) {
                $metadata[$key] = $value;
            }
        }

        $summaryAppend = trim((string) ($options['enquiry_summary_append'] ?? ''));

        if ($summaryAppend !== '') {
            $existingSummary = trim((string) ($lead->enquiry_summary ?? ''));

            if ($existingSummary === '') {
                $updates['enquiry_summary'] = mb_substr($summaryAppend, 0, 500);
            } elseif (! str_contains($existingSummary, $summaryAppend)) {
                $updates['enquiry_summary'] = mb_substr($existingSummary.' | '.$summaryAppend, 0, 500);
            }
        }

        if (! empty($options['log_identity_match'])) {
            $identityMatched = $this->recordIdentityMatch($metadata, $options);
        }

        if ($updates !== [] || $metadata !== ($lead->metadata ?? [])) {
            $lead->fill($updates);

            if ($metadata !== ($lead->metadata ?? [])) {
                $lead->metadata = $metadata;
            }

            $this->qualification->applyToLead($lead, array_merge($lead->toArray(), $updates, [
                'metadata' => $metadata,
                'conversation_id' => $lead->conversation_id,
            ]));

            $lead->save();

            if ($identityMatched) {
                $this->activity->log(
                    $lead->fresh(),
                    LeadActivityType::IdentityMatched,
                    metadata: array_filter([
                        'source' => $options['source'] ?? null,
                        'conversation_id' => $options['conversation_id'] ?? null,
                        'match_type' => $options['match_type'] ?? null,
                    ]),
                    description: $this->identityMatchDescription($options),
                );
            }
        } elseif ($identityMatched) {
            $lead->metadata = $metadata;
            $lead->save();

            $this->activity->log(
                $lead->fresh(),
                LeadActivityType::IdentityMatched,
                metadata: array_filter([
                    'source' => $options['source'] ?? null,
                    'conversation_id' => $options['conversation_id'] ?? null,
                    'match_type' => $options['match_type'] ?? null,
                ]),
                description: $this->identityMatchDescription($options),
            );
        }

        return $lead->fresh();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    private function recordIdentityMatch(array &$metadata, array $options): bool
    {
        $matches = is_array($metadata['identity_matches'] ?? null)
            ? $metadata['identity_matches']
            : [];

        $entry = array_filter([
            'source' => $options['source'] ?? 'unknown',
            'matched_at' => now()->toIso8601String(),
            'conversation_id' => $options['conversation_id'] ?? null,
            'match_type' => $options['match_type'] ?? null,
        ]);

        foreach ($matches as $existing) {
            if (! is_array($existing)) {
                continue;
            }

            if (($existing['source'] ?? null) === ($entry['source'] ?? null)
                && ($existing['conversation_id'] ?? null) === ($entry['conversation_id'] ?? null)) {
                return false;
            }
        }

        $matches[] = $entry;
        $metadata['identity_matches'] = $matches;

        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function identityMatchDescription(array $options): string
    {
        return match ($options['source'] ?? null) {
            'widget_chat', 'widget_conversation' => 'Lead matched and updated from widget chat.',
            'widget_form' => 'Lead matched and updated from widget form.',
            'whatsapp' => 'Lead matched and updated from WhatsApp conversation.',
            default => 'Lead matched and updated from another source.',
        };
    }
}
