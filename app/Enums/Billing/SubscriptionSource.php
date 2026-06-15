<?php

namespace App\Enums\Billing;

enum SubscriptionSource: string
{
    case Manual = 'manual';
    case Trial = 'trial';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Trial => 'Trial',
            self::Platform => 'Platform',
        };
    }
}
