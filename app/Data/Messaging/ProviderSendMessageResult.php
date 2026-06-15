<?php

namespace App\Data\Messaging;

readonly class ProviderSendMessageResult
{
    public function __construct(
        public string $providerMessageId,
        public string $status,
        public ?array $safeMetadata = null,
    ) {}
}
