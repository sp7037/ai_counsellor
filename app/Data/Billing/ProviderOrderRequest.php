<?php

namespace App\Data\Billing;

readonly class ProviderOrderRequest
{
    public function __construct(
        public int $amountMinor,
        public string $currency,
        public string $receiptReference,
        public string $description,
        public array $notes = [],
    ) {}
}
