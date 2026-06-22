<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Support\Str;

class HandoffSummaryService
{
    public function generate(Conversation $conversation): string
    {
        $conversation->loadMissing(['lead', 'messages']);
        $lead = $conversation->lead;
        $metadata = is_array($lead?->metadata) ? $lead->metadata : [];

        $lastVisitorMessage = $conversation->messages()
            ->where('role', MessageRole::Visitor->value)
            ->latest('id')
            ->value('body');

        $lines = [
            'Visitor need: '.$this->visitorNeed($conversation, $lead),
            'Contact: '.$this->contactLine($lead),
            'Interests: '.$this->interestsLine($lead, $metadata),
            'Last unanswered question: '.Str::limit(trim((string) $lastVisitorMessage), 200, '') ?: 'Not captured',
            'Recommended next action: '.$this->recommendedAction($lead, $metadata),
        ];

        return implode("\n", array_filter($lines, fn (string $line) => trim($line) !== ''));
    }

    public function storeForConversation(Conversation $conversation): void
    {
        $conversation->loadMissing('lead');

        if ($conversation->lead === null) {
            return;
        }

        $summary = $this->generate($conversation);
        $existing = trim((string) $conversation->lead->ai_suggested_summary);

        if ($existing === '' || str_starts_with(strtolower($existing), 'visitor requested human')) {
            $conversation->lead->update(['ai_suggested_summary' => $summary]);
        }
    }

    private function visitorNeed(Conversation $conversation, ?Lead $lead): string
    {
        $summary = trim((string) ($lead?->enquiry_summary ?? ''));

        if ($summary !== '' && ! str_starts_with(strtolower($summary), 'visitor requested human')) {
            return Str::limit($summary, 220, '');
        }

        $recent = $conversation->messages()
            ->where('role', MessageRole::Visitor->value)
            ->latest('id')
            ->limit(2)
            ->pluck('body')
            ->map(fn ($body) => trim((string) $body))
            ->filter()
            ->implode(' | ');

        return Str::limit($recent !== '' ? $recent : 'Human support requested during counselling chat.', 220, '');
    }

    private function contactLine(?Lead $lead): string
    {
        if ($lead === null) {
            return 'Not provided';
        }

        return collect([
            $lead->full_name,
            $lead->mobile,
            $lead->email,
        ])->filter()->implode(' · ') ?: 'Not provided';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function interestsLine(?Lead $lead, array $metadata): string
    {
        $parts = array_filter([
            $lead?->programme_interest ? 'Programme '.$lead->programme_interest : null,
            $lead?->service_interest ? 'Service '.$lead->service_interest : null,
            ($lead?->country ?? ($metadata['preferred_country'] ?? null)) ? 'Country '.($lead?->country ?? $metadata['preferred_country']) : null,
            ($metadata['budget'] ?? null) ? 'Budget '.$metadata['budget'] : null,
            ($metadata['neet_status'] ?? null) ? 'NEET '.$metadata['neet_status'] : null,
            ($metadata['timeline'] ?? null) ? 'Timeline '.$metadata['timeline'] : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Not yet captured';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recommendedAction(?Lead $lead, array $metadata): string
    {
        if (blank($lead?->mobile) && blank($lead?->email)) {
            return 'Confirm contact details and verify counselling requirements.';
        }

        if (blank($metadata['neet_status'] ?? null)) {
            return 'Confirm NEET status, budget, and preferred country before recommending options.';
        }

        if (blank($metadata['budget'] ?? null)) {
            return 'Discuss budget-fit countries/institutions and document checklist.';
        }

        return 'Review collected details, answer the last question, and propose next counselling steps.';
    }
}
