<?php

namespace App\Enums\Tenancy;

enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageWidget(): bool
    {
        return $this->canManageMembers();
    }

    public function canManageKnowledge(): bool
    {
        return $this->canManageMembers();
    }

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Staff => 1,
        };
    }

    public function canAssignRole(self $target): bool
    {
        if ($this === self::Owner) {
            return $target !== self::Owner;
        }

        if ($this === self::Admin) {
            return $target === self::Staff;
        }

        return false;
    }
}
