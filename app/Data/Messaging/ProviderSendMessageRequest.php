<?php

namespace App\Data\Messaging;

readonly class ProviderSendMessageRequest
{
    public function __construct(
        public string $recipientPhone,
        public string $body,
        public ?string $replyToProviderMessageId = null,
    ) {}
}
