<?php

namespace App\Data\AI;

readonly class AiResponse
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $content,
        public AiUsage $usage,
        public bool $refused = false,
        public ?string $finishReason = null,
    ) {}
}
