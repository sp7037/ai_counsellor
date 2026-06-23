<?php

namespace App\Services\AI;

use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Lead;

class CounsellingFlowHelper
{
    /** @var array<int, array{key: string, label: string}> */
    private const FIELD_PRIORITY = [
        ['key' => 'neet_status', 'label' => 'NEET status'],
        ['key' => 'budget', 'label' => 'approximate budget'],
        ['key' => 'preferred_country', 'label' => 'preferred country'],
        ['key' => 'student_city_state', 'label' => 'student city/state'],
        ['key' => 'class_12_pcb_marks', 'label' => 'Class 12 PCB marks'],
        ['key' => 'timeline', 'label' => 'target intake/session timeline'],
        ['key' => 'contact_details', 'label' => 'name and mobile for follow-up'],
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     active: bool,
     *     missing: array<int, string>,
     *     next_field: ?string,
     *     next_question: ?string,
     *     should_ask_contact: bool,
     *     visitor_mbbs_messages: int
     * }
     */
    public function assess(Conversation $conversation, string $visitorMessage, array $context): array
    {
        if (! $this->isMbbsAbroadEnquiry($visitorMessage, $context)) {
            return $this->inactiveAssessment();
        }

        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $askedFields = is_array($metadata['counselling_asked_fields'] ?? null)
            ? $metadata['counselling_asked_fields']
            : [];
        $visitorMbbsMessages = $this->countMbbsVisitorMessages($conversation, $visitorMessage, $context);
        $missing = [];

        foreach (self::FIELD_PRIORITY as $field) {
            if ($field['key'] === 'contact_details') {
                continue;
            }

            if ($this->fieldIsCollected($field['key'], $context, $metadata)) {
                continue;
            }

            if (in_array($field['key'], $askedFields, true)) {
                continue;
            }

            $missing[] = $field['label'];
        }

        $shouldAskContact = $this->shouldAskContactDetails(
            $context,
            $metadata,
            $askedFields,
            $visitorMessage,
            $visitorMbbsMessages,
        );

        if ($shouldAskContact) {
            $missing[] = 'name and mobile for follow-up';
        }

        $nextFieldKey = $this->resolveNextFieldKey($missing, $shouldAskContact, $askedFields, $context, $metadata);
        $nextField = $this->labelForKey($nextFieldKey);

        return [
            'active' => true,
            'missing' => $missing,
            'next_field' => $nextField,
            'next_question' => $this->questionForField($nextFieldKey),
            'should_ask_contact' => $shouldAskContact,
            'visitor_mbbs_messages' => $visitorMbbsMessages,
        ];
    }

    public function recordAskedField(?Lead $lead, ?string $fieldKey): void
    {
        if ($lead === null || blank($fieldKey)) {
            return;
        }

        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $asked = is_array($metadata['counselling_asked_fields'] ?? null)
            ? $metadata['counselling_asked_fields']
            : [];

        if (in_array($fieldKey, $asked, true)) {
            return;
        }

        $asked[] = $fieldKey;
        $metadata['counselling_asked_fields'] = $asked;
        $lead->metadata = $metadata;
        $lead->save();
    }

    /**
     * @param  array{active: bool, missing: array<int, string>, next_field: ?string, next_question: ?string, should_ask_contact?: bool, visitor_mbbs_messages?: int}  $assessment
     */
    public function toPromptBlock(array $assessment): string
    {
        if (! $assessment['active']) {
            return '';
        }

        $maxWords = (int) config('ai.counselling_max_words', 120);
        $maxBullets = (int) config('ai.counselling_max_bullets', 4);

        $lines = [
            'Counselling flow (MBBS abroad enquiry detected):',
            "Structure each reply as: (1) one short acknowledgement using collected facts, (2) up to {$maxBullets} concise guidance bullets, (3) exactly one complete next question.",
            "Stay within about {$maxWords} words total. Use plain text with at most {$maxBullets} short bullet points.",
            'Answer the visitor question first using published knowledge when available.',
            'Do not invent exact fees, university names, eligibility rules, deadlines, or guarantees unless present in published knowledge.',
            'If published knowledge lacks specific details, say verified details are needed and give cautious general guidance.',
            'Ask exactly ONE follow-up question at the end. Never ask multiple questions in one reply.',
            'Never end mid-sentence. Never continue long explanations after the follow-up question.',
            'Avoid long country lists unless the visitor explicitly asks for countries.',
            'Do not repeat a follow-up for information already collected or already asked in this conversation.',
            'Do not push human counsellor contact unless the visitor asks or the issue is high-risk.',
            'Do not ask for name, mobile, or email in the first reply unless the visitor asked for callback or admission help.',
            'For location, ask for city/state in plain words. Never claim automatic GPS or location access.',
        ];

        if ($assessment['next_question'] !== null) {
            $lines[] = 'Preferred next follow-up (ask only this one question): '.$assessment['next_question'];
        } elseif ($assessment['should_ask_contact'] ?? false) {
            $lines[] = 'Preferred next follow-up: May I know the student\'s name and mobile number for personalised guidance?';
        } else {
            $lines[] = 'No follow-up question needed right now unless it naturally helps the visitor.';
        }

        if ($assessment['missing'] !== []) {
            $lines[] = 'Information still useful to collect (do not ask all at once): '.implode(', ', $assessment['missing']).'.';
        } else {
            $lines[] = 'Core counselling details appear collected. Offer concise next-step guidance only.';
        }

        return implode("\n", $lines);
    }

    public function fieldKeyFromLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        foreach (self::FIELD_PRIORITY as $field) {
            if ($field['label'] === $label) {
                return $field['key'];
            }
        }

        return null;
    }

    /**
     * @param  array{active: bool, missing: array<int, string>, next_field: ?string, next_question: ?string, should_ask_contact?: bool, visitor_mbbs_messages?: int}  $assessment
     * @param  array<string, mixed>  $context
     */
    public function buildProviderFailureFallback(array $assessment, array $context, string $visitorMessage): ?string
    {
        if (! ($assessment['active'] ?? false)) {
            return null;
        }

        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $parts = array_filter([
            $this->acknowledgementForMessage($visitorMessage),
            $this->briefGuidanceSnippet($context, $metadata),
            $this->resolveFallbackQuestion($assessment),
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array{active: bool, missing: array<int, string>, next_field: ?string, next_question: ?string, should_ask_contact?: bool, visitor_mbbs_messages?: int}  $assessment
     */
    private function resolveFallbackQuestion(array $assessment): ?string
    {
        if ($assessment['should_ask_contact'] ?? false) {
            return $this->questionForField('contact_details');
        }

        if (filled($assessment['next_question'] ?? null)) {
            return (string) $assessment['next_question'];
        }

        $fieldKey = $this->fieldKeyFromLabel($assessment['next_field'] ?? null);

        return $this->questionForField($fieldKey);
    }

    private function acknowledgementForMessage(string $visitorMessage): ?string
    {
        if (preg_match('/\bopen to suggestions\b/i', $visitorMessage)) {
            return 'Thanks, I\'ve noted that you are open to country suggestions.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    private function briefGuidanceSnippet(array $context, array $metadata): ?string
    {
        $facts = $this->collectedFactPhrases($context, $metadata);

        if ($facts === []) {
            return 'I can still help with general MBBS abroad guidance.';
        }

        return 'Based on your '.implode(', ', $facts).', the next useful step is to shortlist only NMC-recognised options and verify current fees directly.';
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     * @return array<int, string>
     */
    private function collectedFactPhrases(array $context, array $metadata): array
    {
        $facts = [];

        if (filled($metadata['neet_score'] ?? null)) {
            $facts[] = 'NEET score of '.$metadata['neet_score'];
        } elseif (filled($context['neet_status'] ?? null)) {
            $facts[] = 'NEET status ('.$context['neet_status'].')';
        }

        if (filled($metadata['class_12_pcb_marks'] ?? null)) {
            $facts[] = 'Class 12 PCB marks of '.$metadata['class_12_pcb_marks'].'%';
        }

        if (filled($context['budget'] ?? null)) {
            $facts[] = 'budget of '.$context['budget'];
        }

        if (filled($context['city_state'] ?? null)) {
            $facts[] = $context['city_state'].' location';
        } elseif (filled($context['location'] ?? null)) {
            $facts[] = $context['location'].' location';
        }

        if (filled($context['timeline'] ?? null)) {
            $facts[] = $context['timeline'].' intake timeline';
        }

        if (($metadata['country_preference'] ?? null) === 'open_to_suggestions') {
            $facts[] = 'open country preference';
        } elseif (filled($context['country'] ?? null)) {
            $facts[] = $context['country'].' country interest';
        }

        return $facts;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    private function fieldIsCollected(string $key, array $context, array $metadata): bool
    {
        return match ($key) {
            'neet_status' => filled($context['neet_status'] ?? null) || filled($metadata['neet_score'] ?? null),
            'budget' => filled($context['budget'] ?? null),
            'preferred_country' => filled($context['country'] ?? null)
                || filled($metadata['preferred_country'] ?? null)
                || filled($metadata['country_preference'] ?? null),
            'student_city_state' => filled($context['city_state'] ?? null)
                || filled($metadata['city_state'] ?? null)
                || filled($context['location'] ?? null),
            'class_12_pcb_marks' => filled($metadata['class_12_pcb_marks'] ?? null),
            'timeline' => filled($context['timeline'] ?? null),
            'contact_details' => $this->contactDetailsCollected($context),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contactDetailsCollected(array $context): bool
    {
        $hasMobile = filled($context['mobile'] ?? null);
        $hasName = filled($context['visitor_name'] ?? null);

        return $hasMobile && $hasName;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     * @param  array<int, string>  $askedFields
     */
    private function shouldAskContactDetails(
        array $context,
        array $metadata,
        array $askedFields,
        string $visitorMessage,
        int $visitorMbbsMessages,
    ): bool {
        if ($this->contactDetailsCollected($context)) {
            return false;
        }

        if (in_array('contact_details', $askedFields, true)) {
            return false;
        }

        if ($this->hasCallbackOrAdmissionIntent($visitorMessage)) {
            return true;
        }

        return $visitorMbbsMessages >= 3;
    }

    /**
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $askedFields
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    private function resolveNextFieldKey(
        array $missing,
        bool $shouldAskContact,
        array $askedFields,
        array $context,
        array $metadata,
    ): ?string {
        foreach (self::FIELD_PRIORITY as $field) {
            if ($field['key'] === 'contact_details') {
                if ($shouldAskContact && ! in_array('contact_details', $askedFields, true)) {
                    return 'contact_details';
                }

                continue;
            }

            if (! in_array($field['label'], $missing, true)) {
                continue;
            }

            if (! $this->fieldIsCollected($field['key'], $context, $metadata)
                && ! in_array($field['key'], $askedFields, true)) {
                return $field['key'];
            }
        }

        return null;
    }

    private function labelForKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        foreach (self::FIELD_PRIORITY as $field) {
            if ($field['key'] === $key) {
                return $field['label'];
            }
        }

        return null;
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function countMbbsVisitorMessages(Conversation $conversation, string $visitorMessage, array $context): int
    {
        $count = $conversation->messages()
            ->where('role', MessageRole::Visitor->value)
            ->count();

        if ($this->isMbbsAbroadEnquiry($visitorMessage, $context)) {
            $count += 1;
        }

        return $count;
    }

    private function hasCallbackOrAdmissionIntent(string $message): bool
    {
        return (bool) preg_match(
            '/\b(?:call\s*me|callback|admission|apply|enrol|enroll|next\s+step|personalized|personalised)\b/i',
            $message,
        );
    }

    private function questionForField(?string $key): ?string
    {
        return match ($key) {
            'neet_status' => 'What is your NEET status or score?',
            'budget' => 'What is your approximate total budget range for tuition and living costs?',
            'preferred_country' => 'Which country are you most interested in, or are you open to suggestions?',
            'student_city_state' => 'Which city and state is the student from?',
            'class_12_pcb_marks' => 'What were your Class 12 PCB marks or percentage, if available?',
            'timeline' => 'Which intake or academic session are you targeting?',
            'contact_details' => 'May I know the student\'s name and mobile number for personalised guidance?',
            default => null,
        };
    }

    /**
     * @return array{active: bool, missing: array<int, string>, next_field: ?string, next_question: ?string, should_ask_contact: bool, visitor_mbbs_messages: int}
     */
    private function inactiveAssessment(): array
    {
        return [
            'active' => false,
            'missing' => [],
            'next_field' => null,
            'next_question' => null,
            'should_ask_contact' => false,
            'visitor_mbbs_messages' => 0,
        ];
    }
}
