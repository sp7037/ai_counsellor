<?php

namespace App\Services\Leads;

use App\Models\Lead;

class LeadMetadataUpdateService
{
    public function __construct(
        private readonly LeadQualificationEngine $qualification,
        private readonly LeadNameGuard $nameGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $extracted
     */
    public function mergeExtractedData(Lead $lead, array $extracted): Lead
    {
        $updates = [];

        if (! empty($extracted['full_name']) && $this->nameGuard->shouldReplaceExistingName($lead->full_name, (string) $extracted['full_name'])) {
            $updates['full_name'] = trim((string) $extracted['full_name']);
        }

        if (! empty($extracted['mobile']) && blank($lead->mobile)) {
            $updates['mobile'] = preg_replace('/\D+/', '', (string) $extracted['mobile']) ?: null;
        }

        if (! empty($extracted['email']) && blank($lead->email)) {
            $updates['email'] = strtolower(trim((string) $extracted['email']));
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

            if (! array_key_exists($key, $metadata) || blank($metadata[$key])) {
                $metadata[$key] = $value;
            }
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
        }

        return $lead->fresh();
    }
}
