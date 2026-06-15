<?php

namespace App\Data\AI;

readonly class AiUsage
{
    public function __construct(
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $totalTokens = null,
        public ?int $latencyMs = null,
    ) {}
}
