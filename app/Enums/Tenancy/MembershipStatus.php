<?php

namespace App\Enums\Tenancy;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }
}
