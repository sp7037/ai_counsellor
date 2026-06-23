<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadSource;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Tenant;

class ChatLeadExtractionService
{
    public function __construct(
        private readonly LeadCreationService $creation,
        private readonly LeadMetadataUpdateService $metadataUpdate,
        private readonly LeadNameGuard $nameGuard,
        private readonly LeadIdentityResolver $identity,
    ) {}

    public function processMessage(Tenant $tenant, Conversation $conversation, string $message): ?Lead
    {
        $extracted = $this->extractFromMessage($message);

        if ($extracted === []) {
            return $conversation->lead;
        }

        $conversation->loadMissing('lead');

        $matched = $this->identity->resolve(
            $tenant,
            $extracted['mobile'] ?? null,
            $extracted['email'] ?? null,
            $conversation,
        );

        if ($matched !== null && $this->hasResolvableIdentity($extracted, $matched, $conversation)) {
            if ($conversation->lead_id !== $matched->id) {
                $this->identity->linkConversation($conversation, $matched);
            }

            return $this->metadataUpdate->mergeExtractedData($matched, $extracted, [
                'source' => 'widget_chat',
                'log_identity_match' => $conversation->lead_id !== $matched->id
                    || $this->identity->normalizeMobile($extracted['mobile'] ?? null) !== null
                    || $this->identity->normalizeEmail($extracted['email'] ?? null) !== null,
                'conversation_id' => $conversation->id,
                'match_type' => $this->resolveMatchType($extracted),
                'enquiry_summary_append' => $this->buildEnquirySummary($message, $extracted),
            ]);
        }

        if ($conversation->lead !== null) {
            return $this->metadataUpdate->mergeExtractedData($conversation->lead, $extracted, [
                'enquiry_summary_append' => $this->buildEnquirySummary($message, $extracted),
            ]);
        }

        if (! $this->shouldCreateLead($extracted, $message)) {
            return null;
        }

        return $this->creation->create(
            $tenant,
            LeadSource::WidgetConversation,
            array_merge([
                'conversation_id' => $conversation->id,
                'full_name' => $this->nameGuard->storedName($extracted['full_name'] ?? null),
                'mobile' => $extracted['mobile'] ?? null,
                'email' => $extracted['email'] ?? null,
                'country' => $extracted['country'] ?? null,
                'service_interest' => $extracted['service_interest'] ?? null,
                'programme_interest' => $extracted['programme_interest'] ?? null,
                'enquiry_summary' => $this->buildEnquirySummary($message, $extracted),
                'metadata' => $extracted['metadata'] ?? null,
            ], array_filter([
                'requested_human_contact' => ! empty($extracted['requested_human_contact']),
            ])),
            sourceReference: 'chat:'.$conversation->uuid,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromMessage(string $message): array
    {
        $message = trim($message);

        if ($message === '') {
            return [];
        }

        $extracted = [];

        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
            $extracted['email'] = strtolower($matches[0]);
        }

        $compact = preg_replace('/\s+/', '', $message) ?? $message;

        if (preg_match('/(?:\+91|91)?([6-9]\d{9})/', $compact, $matches)) {
            $extracted['mobile'] = $matches[1];
        }

        $extractedName = $this->nameGuard->extractFromMessage($message);

        if ($extractedName !== null) {
            $extracted['full_name'] = $extractedName;
        }

        if (preg_match('/\b(mbbs|bds|md|ms)\b/i', $message, $matches)) {
            $extracted['programme_interest'] = strtoupper($matches[1]);
            $extracted['service_interest'] = 'MBBS Abroad';
        }

        $countries = [
            'georgia', 'philippines', 'russia', 'ukraine', 'kyrgyzstan', 'kazakhstan',
            'nepal', 'bangladesh', 'uzbekistan', 'armenia', 'belarus',
        ];

        foreach ($countries as $country) {
            if (stripos($message, $country) !== false) {
                $extracted['country'] = ucfirst($country);
                $extracted['metadata']['preferred_country'] = ucfirst($country);
                break;
            }
        }

        if (preg_match('/\bopen to suggestions\b/i', $message)) {
            $extracted['metadata']['country_preference'] = 'open_to_suggestions';
            $extracted['metadata']['preferred_country'] = 'Open to suggestions';
        }

        if (preg_match('/\b(?:budget|fee)\b[^.]{0,40}?(\d+(?:\.\d+)?\s*(?:lakh|lakhs|crore|crores)?)/i', $message, $matches)) {
            $extracted['metadata']['budget'] = trim($matches[0]);
        } elseif (preg_match('/\b(\d+(?:\.\d+)?\s*(?:lakh|lakhs|crore|crores))\b/i', $message, $matches)) {
            $extracted['metadata']['budget'] = trim($matches[1]);
        }

        if (preg_match('/neet\s*(?:score|marks)?\s*[:\-]?\s*(\d{2,3})/i', $message, $matches)) {
            $extracted['metadata']['neet_score'] = $matches[1];
            $extracted['metadata']['neet_status'] = 'scored';
        } elseif (preg_match('/\b(?:qualified|cleared|passed)\s+(?:in\s+)?neet\b/i', $message)) {
            $extracted['metadata']['neet_status'] = 'qualified';
        } elseif (preg_match('/\b(?:not\s+(?:appeared|given|attempted)|did not appear)\s+(?:for\s+)?neet\b/i', $message)) {
            $extracted['metadata']['neet_status'] = 'not_appeared';
        } elseif (preg_match('/\bneet\b/i', $message) && preg_match('/\b(?:not qualified|failed|could not clear)\b/i', $message)) {
            $extracted['metadata']['neet_status'] = 'not_qualified';
        }

        if (preg_match('/(?:pcb|class\s*12|12th).{0,25}?(\d{2,3})\s*(?:%|percent|marks)?/i', $message, $matches)) {
            $extracted['metadata']['class_12_pcb_marks'] = $matches[1];
        }

        if (preg_match('/\b(?:this year|next year|202[5-9]\s*(?:intake|session)?|urgent|asap|immediately|current session)\b/i', $message, $matches)) {
            $extracted['metadata']['timeline'] = trim($matches[0]);
        }

        if (preg_match('/\b(?:parent|guardian|mother|father)\b/i', $message)) {
            $extracted['metadata']['contact_preference'] = 'parent';
        } elseif (preg_match('/\b(?:student|myself|i am the student)\b/i', $message)) {
            $extracted['metadata']['contact_preference'] = 'student';
        }

        if (preg_match('/\b(?:documents ready|passport ready|transcripts ready|all documents)\b/i', $message)) {
            $extracted['metadata']['document_readiness'] = 'ready';
        } elseif (preg_match('/\b(?:documents not ready|need help with documents|no passport)\b/i', $message)) {
            $extracted['metadata']['document_readiness'] = 'not_ready';
        }

        if (preg_match('/\b(?:talk to counsellor|human help|call me|contact me|speak to counsellor|whatsapp|callback)\b/i', $message)) {
            $extracted['requested_human_contact'] = true;
        }

        if (preg_match('/\b(?:from|based in|located in|i am from|i am in|i live in)\s+([A-Za-z][A-Za-z\s]{1,40}?)(?:,\s*([A-Za-z][A-Za-z\s]{1,40}))?\b/i', $message, $matches)) {
            $city = trim($matches[1]);
            $state = isset($matches[2]) ? trim($matches[2]) : null;
            $extracted['metadata']['city_state'] = $state ? $city.', '.$state : $city;

            if (blank($extracted['location'] ?? null)) {
                $extracted['location'] = $city;
            }
        }

        return $extracted;
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function hasResolvableIdentity(array $extracted, Lead $matched, Conversation $conversation): bool
    {
        if ($this->identity->normalizeMobile($extracted['mobile'] ?? null) !== null) {
            return true;
        }

        if ($this->identity->normalizeEmail($extracted['email'] ?? null) !== null) {
            return true;
        }

        return $conversation->lead_id !== null
            && $conversation->lead_id === $matched->id;
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function resolveMatchType(array $extracted): ?string
    {
        if ($this->identity->normalizeMobile($extracted['mobile'] ?? null) !== null) {
            return 'mobile';
        }

        if ($this->identity->normalizeEmail($extracted['email'] ?? null) !== null) {
            return 'email';
        }

        return 'conversation';
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function shouldCreateLead(array $extracted, string $message): bool
    {
        if (! empty($extracted['mobile']) || ! empty($extracted['email'])) {
            return true;
        }

        if (! empty($extracted['full_name']) && $this->hasCounsellingIntent($extracted, $message)) {
            return true;
        }

        if ($this->hasCounsellingIntent($extracted, $message)) {
            return true;
        }

        return ! empty($extracted['requested_human_contact']);
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function hasCounsellingIntent(array $extracted, string $message): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $message,
            (string) ($extracted['programme_interest'] ?? ''),
            (string) ($extracted['service_interest'] ?? ''),
        ])));

        return str_contains($haystack, 'mbbs')
            || str_contains($haystack, 'medical abroad')
            || str_contains($haystack, 'study medicine');
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function buildEnquirySummary(string $message, array $extracted): string
    {
        $parts = [trim($message)];

        if (! empty($extracted['programme_interest'])) {
            $parts[] = 'Programme: '.$extracted['programme_interest'];
        }

        if (! empty($extracted['country'])) {
            $parts[] = 'Country: '.$extracted['country'];
        }

        return mb_substr(implode(' | ', array_filter($parts)), 0, 500);
    }
}
