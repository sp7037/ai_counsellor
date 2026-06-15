<?php

namespace App\Enums\Billing;

enum PaymentEnvironment: string
{
    case Test = 'test';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Test',
            self::Live => 'Live',
        };
    }
}
