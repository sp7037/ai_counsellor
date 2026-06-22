<?php

namespace App\Services\AI;

use App\Data\AI\AiMessage;
use Illuminate\Support\Str;

class AiReplyCompletionGuard
{
    /**
     * @param  array<AiMessage>  $messages
     */
    public function extractPreferredFollowUp(array $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message->role !== 'system') {
                continue;
            }

            if (! preg_match('/preferred next follow-up(?: \(ask only this one question\))?:\s*(.+?)(?:\n|$)/i', $message->content, $matches)) {
                continue;
            }

            $question = trim($matches[1]);

            if ($question === '' || str_contains(strtolower($question), 'no follow-up question needed')) {
                return null;
            }

            return str_ends_with($question, '?') ? $question : $question.'?';
        }

        return null;
    }

    public function shouldRetryForTruncation(string $content, ?string $finishReason): bool
    {
        return $finishReason === 'length' || $this->looksIncomplete($content, $finishReason);
    }

    public function looksIncomplete(string $content, ?string $finishReason = null): bool
    {
        $content = trim($content);

        if ($content === '') {
            return true;
        }

        if ($finishReason === 'length') {
            return true;
        }

        if ($this->endsComplete($content)) {
            return false;
        }

        return $this->hasDanglingEnding($content);
    }

    public function finalize(string $content, ?string $finishReason = null, ?string $preferredFollowUp = null): string
    {
        $content = trim(strip_tags($content));

        if ($content === '') {
            return $this->fallbackReply($preferredFollowUp);
        }

        if (! $this->looksIncomplete($content, $finishReason)) {
            return Str::limit($this->ensureFollowUpQuestion($content, $preferredFollowUp), (int) config('ai.max_output_chars', 3000), '');
        }

        $repaired = $this->trimToLastCompleteSentence($content);

        if ($repaired === '' || strlen($repaired) < 20) {
            $repaired = $this->fallbackReply($preferredFollowUp);
        } else {
            $repaired = $this->ensureFollowUpQuestion($repaired, $preferredFollowUp);
        }

        return Str::limit($repaired, (int) config('ai.max_output_chars', 3000), '');
    }

    public function wordCount(string $content): int
    {
        return str_word_count(strip_tags($content));
    }

    public function retryInstruction(): string
    {
        return implode("\n", [
            'Your previous reply was cut off before finishing.',
            'Reply again in at most 120 words using at most 4 short bullet points.',
            'End with exactly one complete follow-up question.',
            'Never stop mid-sentence or mid-list.',
        ]);
    }

    private function endsComplete(string $content): bool
    {
        return (bool) preg_match('/[.!?][\'")\]]*\s*$/u', $content);
    }

    private function hasDanglingEnding(string $content): bool
    {
        if (preg_match('/[,;:\-—]\s*$/u', $content)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(and|or|because|otherwise|but|if|when|as|to|for|with|including|that|which|who|where|while|although|since|unless)\s*$/i',
            $content,
        );
    }

    private function trimToLastCompleteSentence(string $content): string
    {
        $content = rtrim(trim($content), ",;:-—");

        if (preg_match_all('/[^.!?]*[.!?]+/', $content, $matches)) {
            $sentences = array_values(array_filter(array_map('trim', $matches[0])));

            if ($sentences !== []) {
                return (string) end($sentences);
            }
        }

        return '';
    }

    private function ensureFollowUpQuestion(string $content, ?string $preferredFollowUp): string
    {
        if ($preferredFollowUp === null) {
            return $content;
        }

        if (preg_match('/\?\s*$/', $content)) {
            return $content;
        }

        return rtrim($content, '.!').' '.$preferredFollowUp;
    }

    private function fallbackReply(?string $preferredFollowUp): string
    {
        $base = 'I can share cautious MBBS abroad guidance based on what you have shared so far. Specific fees and university options must be verified case by case.';

        if ($preferredFollowUp !== null) {
            return $base.' '.$preferredFollowUp;
        }

        return $base.' What would you like to clarify next?';
    }
}
