<?php

namespace App\Data\Billing;

readonly class ProviderOrderResult
{
    public function __construct(
        public string $providerOrderId,
        public int $amountMinor,
        public string $currency,
        public string $status,
        public ?array $safeMetadata = null,
    ) {}
}
