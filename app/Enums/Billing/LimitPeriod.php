<?php

namespace App\Enums\Billing;

enum LimitPeriod: string
{
    case BillingPeriod = 'billing_period';
    case Total = 'total';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::BillingPeriod => 'Per billing period',
            self::Total => 'Total',
            self::Monthly => 'Per month',
        };
    }
}
