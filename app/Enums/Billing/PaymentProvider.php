<?php

namespace App\Enums\Billing;

enum PaymentProvider: string
{
    case Razorpay = 'razorpay';
    case Fake = 'fake';

    public function label(): string
    {
        return match ($this) {
            self::Razorpay => 'Razorpay',
            self::Fake => 'Fake (testing)',
        };
    }
}
