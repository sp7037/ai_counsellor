<?php

namespace App\Services\AI;

use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use Illuminate\Support\Str;

class ConversationContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Conversation $conversation): array
    {
        $conversation->loadMissing('lead');

        $lead = $conversation->lead;
        $metadata = is_array($lead?->metadata) ? $lead->metadata : [];

        return [
            'visitor_name' => $this->visitorName($lead?->full_name),
            'mobile' => $lead?->mobile,
            'email' => $lead?->email,
            'lead_reference' => $lead?->public_reference,
            'lead_stage' => $lead?->stage?->label(),
            'service_interest' => $lead?->service_interest,
            'programme_interest' => $lead?->programme_interest,
            'country' => $lead?->country ?? ($metadata['preferred_country'] ?? null),
            'budget' => $metadata['budget'] ?? null,
            'neet_status' => $metadata['neet_status'] ?? null,
            'timeline' => $metadata['timeline'] ?? null,
            'message_summary' => $this->recentMessageSummary($conversation),
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function toPromptBlock(array $context): string
    {
        $lines = [
            'Visitor context (internal reference only — never expose lead reference codes or admin notes to the visitor):',
        ];

        $details = array_filter([
            'Name' => $context['visitor_name'] ?? null,
            'Mobile' => $context['mobile'] ?? null,
            'Email' => $context['email'] ?? null,
            'Lead reference' => $context['lead_reference'] ?? null,
            'Lead stage' => $context['lead_stage'] ?? null,
            'Service interest' => $context['service_interest'] ?? null,
            'Programme interest' => $context['programme_interest'] ?? null,
            'Country interest' => $context['country'] ?? null,
            'Budget' => $context['budget'] ?? null,
            'NEET status' => $context['neet_status'] ?? null,
            'Timeline' => $context['timeline'] ?? null,
        ], fn ($value) => filled($value));

        if ($details === []) {
            $lines[] = '- No visitor profile details collected yet.';
        } else {
            foreach ($details as $label => $value) {
                $lines[] = '- '.$label.': '.Str::limit((string) $value, 120, '');
            }
        }

        if (! empty($context['message_summary'])) {
            $lines[] = '- Recent messages: '.Str::limit((string) $context['message_summary'], 400, '');
        }

        return implode("\n", $lines);
    }

    private function visitorName(?string $name): ?string
    {
        $name = trim((string) $name);
        $lower = strtolower($name);

        if ($name === '' || in_array($lower, ['unknown', 'visitor'], true)) {
            return null;
        }

        return $name;
    }

    private function recentMessageSummary(Conversation $conversation): string
    {
        $messages = $conversation->messages()
            ->whereIn('role', [
                MessageRole::Visitor->value,
                MessageRole::Assistant->value,
                MessageRole::Counsellor->value,
            ])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return '';
        }

        return $messages
            ->map(fn ($message) => $message->role->label().': '.Str::limit(trim((string) $message->body), 120, ''))
            ->implode(' | ');
    }
}
