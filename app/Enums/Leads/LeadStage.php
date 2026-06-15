<?php

namespace App\Enums\Leads;

enum LeadStage: string
{
    case New = 'new';
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case ContactAttempted = 'contact_attempted';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case FollowUpRequired = 'follow_up_required';
    case InProgress = 'in_progress';
    case Converted = 'converted';
    case Closed = 'closed';
    case Lost = 'lost';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Unassigned => 'Unassigned',
            self::Assigned => 'Assigned',
            self::ContactAttempted => 'Contact attempted',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::FollowUpRequired => 'Follow-up required',
            self::InProgress => 'In progress',
            self::Converted => 'Converted',
            self::Closed => 'Closed',
            self::Lost => 'Lost',
            self::Invalid => 'Invalid',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Converted, self::Closed, self::Lost, self::Invalid], true);
    }
}
