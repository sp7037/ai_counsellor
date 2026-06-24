<?php

namespace App\Enums\Tenancy;

enum TenantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived',
            self::Deleted => 'Deleted',
        };
    }

    public function allowsTenantAccess(): bool
    {
        return $this === self::Active;
    }

    public function allowsWorkspaceEntry(): bool
    {
        return in_array($this, [self::Active, self::Suspended, self::Pending], true);
    }

    public function canBeArchived(): bool
    {
        return in_array($this, [self::Suspended, self::Cancelled], true);
    }

    public function canBeDeleted(): bool
    {
        return $this === self::Archived;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Archived, self::Deleted], true);
    }
}
