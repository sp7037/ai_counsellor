<?php

namespace App\Services\AI;

use App\Models\Conversation;

class CounsellingFlowHelper
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{active: bool, missing: array<int, string>, next_field: ?string, next_question: ?string}
     */
    public function assess(Conversation $conversation, string $visitorMessage, array $context): array
    {
        if (! $this->isMbbsAbroadEnquiry($visitorMessage, $context)) {
            return [
                'active' => false,
                'missing' => [],
                'next_field' => null,
                'next_question' => null,
            ];
        }

        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $missing = [];

        if (blank($context['neet_status'] ?? null) && blank($metadata['neet_score'] ?? null)) {
            $missing[] = 'NEET status';
        }

        if (blank($context['budget'] ?? null)) {
            $missing[] = 'approximate budget';
        }

        if (blank($context['country'] ?? null)) {
            $missing[] = 'preferred country';
        }

        if (blank($metadata['class_12_pcb_marks'] ?? null)) {
            $missing[] = 'Class 12 PCB marks (if available)';
        }

        if (blank($context['timeline'] ?? null)) {
            $missing[] = 'target intake/session timeline';
        }

        if (blank($metadata['contact_preference'] ?? null)) {
            $missing[] = 'parent or student contact preference';
        }

        if (blank($metadata['document_readiness'] ?? null)) {
            $missing[] = 'document readiness';
        }

        $nextField = $missing[0] ?? null;

        return [
            'active' => true,
            'missing' => $missing,
            'next_field' => $nextField,
            'next_question' => $this->questionForField($nextField),
        ];
    }

    /**
     * @param  array{active: bool, missing: array<int, string>, next_field: ?string, next_question: ?string}  $assessment
     */
    public function toPromptBlock(array $assessment): string
    {
        if (! $assessment['active']) {
            return '';
        }

        $lines = [
            'Counselling flow (MBBS abroad enquiry detected):',
            'Answer the visitor question first using published knowledge when available.',
            'Then ask exactly ONE useful follow-up question. Do not ask multiple questions at once.',
            'Do not show internal source labels, reference codes, or admin terminology to the visitor.',
        ];

        if ($assessment['next_question'] !== null) {
            $lines[] = 'Preferred next follow-up: '.$assessment['next_question'];
        }

        if ($assessment['missing'] !== []) {
            $lines[] = 'Information still useful to collect: '.implode(', ', $assessment['missing']).'.';
        } else {
            $lines[] = 'Core counselling details appear collected. Offer concise next-step guidance or human handoff if needed.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isMbbsAbroadEnquiry(string $visitorMessage, array $context): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $visitorMessage,
            (string) ($context['programme_interest'] ?? ''),
            (string) ($context['service_interest'] ?? ''),
            (string) ($context['message_summary'] ?? ''),
        ])));

        if (str_contains($haystack, 'mbbs') || str_contains($haystack, 'medical abroad') || str_contains($haystack, 'study medicine')) {
            return true;
        }

        return str_contains($haystack, 'abroad') && (
            str_contains($haystack, 'doctor')
            || str_contains($haystack, 'medicine')
            || str_contains($haystack, 'neet')
        );
    }

    private function questionForField(?string $field): ?string
    {
        return match ($field) {
            'NEET status' => 'To guide you better, please tell me your NEET status and approximate budget.',
            'approximate budget' => 'What is your approximate budget for tuition and living costs?',
            'preferred country' => 'Which country are you most interested in for MBBS abroad?',
            'Class 12 PCB marks (if available)' => 'What were your Class 12 PCB marks or percentage, if available?',
            'target intake/session timeline' => 'Which intake or academic session are you targeting?',
            'parent or student contact preference' => 'Should we primarily coordinate with the student or a parent/guardian?',
            'document readiness' => 'Do you already have passport, academic transcripts, and NEET-related documents ready?',
            default => null,
        };
    }
}
