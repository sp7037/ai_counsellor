<?php

namespace App\Enums\Leads;

enum LeadTaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Overdue => 'Overdue',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress, self::Overdue], true);
    }
}
