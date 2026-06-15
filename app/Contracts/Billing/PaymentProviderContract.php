<?php

namespace App\Contracts\Billing;

use App\Data\Billing\ProviderOrderRequest;
use App\Data\Billing\ProviderOrderResult;
use App\Enums\Billing\PaymentEnvironment;
use App\Enums\Billing\PaymentProvider;

interface PaymentProviderContract
{
    public function provider(): PaymentProvider;

    public function environment(): PaymentEnvironment;

    public function publicKeyId(): string;

    public function createOrder(ProviderOrderRequest $request): ProviderOrderResult;

    public function verifyPaymentSignature(string $providerOrderId, string $providerPaymentId, string $signature): bool;

    public function verifyWebhookSignature(string $rawBody, string $signature): bool;

    public function fetchOrder(string $providerOrderId): ?array;
}
