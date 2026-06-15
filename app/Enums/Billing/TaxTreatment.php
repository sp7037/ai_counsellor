<?php

namespace App\Enums\Billing;

enum TaxTreatment: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    public function label(): string
    {
        return match ($this) {
            self::Inclusive => 'Tax inclusive',
            self::Exclusive => 'Tax exclusive',
        };
    }
}
