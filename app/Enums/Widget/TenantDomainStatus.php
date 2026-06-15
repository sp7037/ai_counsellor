<?php

namespace App\Enums\Widget;

enum TenantDomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Blocked => 'Blocked',
        };
    }

    public function allowsWidget(): bool
    {
        return $this === self::Verified;
    }
}
