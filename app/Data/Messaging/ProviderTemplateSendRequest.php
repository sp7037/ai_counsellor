<?php

namespace App\Data\Messaging;

readonly class ProviderTemplateSendRequest
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    public function __construct(
        public string $recipientPhone,
        public string $templateName,
        public string $languageCode = 'en',
        public array $components = [],
    ) {}
}
