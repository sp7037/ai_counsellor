<?php

namespace App\Data\AI;

readonly class AiRequest
{
    /**
     * @param  array<AiMessage>  $messages
     */
    public function __construct(
        public string $provider,
        public string $model,
        public array $messages,
        public float $temperature,
        public int $maxOutputTokens,
        public int $timeoutSeconds,
        public ?string $requestId = null,
        public ?string $apiKey = null,
    ) {}
}
