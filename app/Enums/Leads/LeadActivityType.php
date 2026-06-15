<?php

namespace App\Enums\Leads;

enum LeadActivityType: string
{
    case Created = 'created';
    case QualificationUpdated = 'qualification_updated';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case ContactAttempt = 'contact_attempt';
    case Contacted = 'contacted';
    case StageChanged = 'stage_changed';
    case PriorityChanged = 'priority_changed';
    case NoteAdded = 'note_added';
    case FollowUpScheduled = 'follow_up_scheduled';
    case FollowUpCompleted = 'follow_up_completed';
    case FollowUpRescheduled = 'follow_up_rescheduled';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case MarkedLost = 'marked_lost';
    case MarkedInvalid = 'marked_invalid';
    case Converted = 'converted';
    case AdminOverride = 'admin_override';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Lead created',
            self::QualificationUpdated => 'Qualification updated',
            self::Assigned => 'Assigned',
            self::Reassigned => 'Reassigned',
            self::ContactAttempt => 'Contact attempt',
            self::Contacted => 'Contacted',
            self::StageChanged => 'Stage changed',
            self::PriorityChanged => 'Priority changed',
            self::NoteAdded => 'Note added',
            self::FollowUpScheduled => 'Follow-up scheduled',
            self::FollowUpCompleted => 'Follow-up completed',
            self::FollowUpRescheduled => 'Follow-up rescheduled',
            self::Closed => 'Closed',
            self::Reopened => 'Reopened',
            self::MarkedLost => 'Marked lost',
            self::MarkedInvalid => 'Marked invalid',
            self::Converted => 'Converted',
            self::AdminOverride => 'Admin override',
        };
    }
}
