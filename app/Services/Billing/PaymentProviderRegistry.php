<?php

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentProviderContract;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentProvider;
use App\Exceptions\Billing\PaymentException;
use App\Services\Billing\Providers\FakePaymentProvider;
use App\Services\Billing\Providers\RazorpayPaymentProvider;

class PaymentProviderRegistry
{
    public function __construct(
        private readonly PaymentCredentialsService $credentials,
        private readonly RazorpayPaymentProvider $razorpay,
        private readonly FakePaymentProvider $fake,
    ) {}

    public function resolve(?PaymentProvider $provider = null): PaymentProviderContract
    {
        $provider ??= $this->credentials->activeProvider();

        return match ($provider) {
            PaymentProvider::Razorpay => $this->razorpay,
            PaymentProvider::Fake => $this->fake,
            default => throw new PaymentException('Unsupported payment provider.', PaymentFailureCategory::ProviderUnavailable),
        };
    }
}
