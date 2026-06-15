<?php

namespace App\Services\Knowledge;

use App\Services\Configuration\ConfigurationValidator;
use Illuminate\Validation\ValidationException;

class KnowledgeContentSanitizer
{
    public function __construct(
        private readonly ConfigurationValidator $validator,
    ) {}

    public function title(?string $value): string
    {
        $sanitized = $this->validator->sanitizePlainText($value, config('knowledge.max_title_length', 200));

        if ($sanitized === null || $sanitized === '') {
            throw ValidationException::withMessages([
                'title' => 'Title is required.',
            ]);
        }

        return $sanitized;
    }

    public function body(?string $value): string
    {
        $sanitized = $this->validator->sanitizePlainText($value, config('knowledge.max_body_length', 20000));

        if ($sanitized === null || $sanitized === '') {
            throw ValidationException::withMessages([
                'body' => 'Content is required.',
            ]);
        }

        return $sanitized;
    }

    public function optionalText(?string $value, int $maxLength): ?string
    {
        return $this->validator->sanitizePlainText($value, $maxLength);
    }
}
