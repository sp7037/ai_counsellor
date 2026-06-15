<?php

namespace App\Enums\Leads;

enum LeadQualificationStatus: string
{
    case NotReviewed = 'not_reviewed';
    case Potential = 'potential';
    case Qualified = 'qualified';
    case Unqualified = 'unqualified';
    case InsufficientInformation = 'insufficient_information';

    public function label(): string
    {
        return match ($this) {
            self::NotReviewed => 'Not reviewed',
            self::Potential => 'Potential',
            self::Qualified => 'Qualified',
            self::Unqualified => 'Unqualified',
            self::InsufficientInformation => 'Insufficient information',
        };
    }
}
