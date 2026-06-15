<?php

namespace App\Data\Messaging;

readonly class InboundMessageData
{
    public function __construct(
        public string $providerMessageId,
        public string $senderPhone,
        public string $body,
        public ?string $senderName = null,
        public ?string $replyToProviderMessageId = null,
        public ?string $messageType = 'text',
    ) {}
}
