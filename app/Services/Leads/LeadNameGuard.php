<?php

namespace App\Services\Leads;

class LeadNameGuard
{
    /** @var list<string> */
    private const WEAK_KEYWORDS = [
        'mbbs', 'bds', 'neet', 'abroad', 'planning', 'qualified', 'budget', 'targeting',
        'guide', 'suggestion', 'counsellor', 'counselor', 'visitor', 'student', 'parent',
        'question', 'help', 'interested', 'looking', 'want', 'need', 'please', 'can you',
        'could you', 'tell me', 'what', 'how', 'when', 'where', 'which', 'about',
    ];

    /** @var list<string> */
    private const PLACEHOLDER_NAMES = [
        'unknown', 'visitor', 'open to suggestions', 'targeting',
    ];

    public function extractFromMessage(string $message): ?string
    {
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        if (preg_match('/\b(?:my name is|name is)\s+([A-Za-z][A-Za-z\s\'.-]+?)(?:\s+and\s+(?:my\s+)?(?:mobile|phone|email|number)\b|[.,;]|$)/iu', $message, $matches)) {
            return $this->normalizeCandidate($matches[1]);
        }

        if (preg_match('/\bthis is\s+([A-Za-z][A-Za-z\s\'.-]+?)(?:\s+and\s+|\s+from\s+|[.,;]|$)/iu', $message, $matches)) {
            return $this->normalizeCandidate($matches[1]);
        }

        if (preg_match('/\bi am\s+([A-Za-z][A-Za-z\s\'.-]+?)(?:\s+and\s+|\s+from\s+|[.,;]|$)/iu', $message, $matches)) {
            return $this->normalizeCandidate($matches[1]);
        }

        return null;
    }

    public function normalizeCandidate(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $name = trim($name, " \t\n\r\0\x0B'\".,;");

        if ($name === '') {
            return null;
        }

        return $this->isValidPersonName($name) ? $name : null;
    }

    public function isValidPersonName(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 50) {
            return false;
        }

        if (preg_match('/\d/', $name)) {
            return false;
        }

        if (str_word_count($name) > 5) {
            return false;
        }

        $lower = strtolower($name);

        if (in_array($lower, self::PLACEHOLDER_NAMES, true)) {
            return false;
        }

        foreach (self::WEAK_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return false;
            }
        }

        if (! preg_match("/^[A-Za-z][A-Za-z\s'.-]+$/u", $name)) {
            return false;
        }

        return true;
    }

    public function isWeakLeadName(?string $name): bool
    {
        if ($name === null) {
            return true;
        }

        $name = trim($name);

        if ($name === '') {
            return true;
        }

        $lower = strtolower($name);

        if (in_array($lower, self::PLACEHOLDER_NAMES, true)) {
            return true;
        }

        return ! $this->isValidPersonName($name);
    }

    public function shouldReplaceExistingName(?string $existing, ?string $incoming): bool
    {
        if (! $this->isValidPersonName($incoming)) {
            return false;
        }

        return $this->isWeakLeadName($existing);
    }

    public function storedName(?string $candidate): string
    {
        return $this->isValidPersonName($candidate) ? trim((string) $candidate) : 'Visitor';
    }

    public function contactLabel(?string $name, ?string $mobile, ?string $email): string
    {
        if ($this->isValidPersonName($name)) {
            return trim((string) $name);
        }

        if (filled($mobile)) {
            return (string) $mobile;
        }

        if (filled($email)) {
            return (string) $email;
        }

        return 'Visitor';
    }
}
