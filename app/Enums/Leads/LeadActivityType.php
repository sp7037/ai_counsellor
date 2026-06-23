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
    case TaskCreated = 'task_created';
    case TaskStarted = 'task_started';
    case TaskCompleted = 'task_completed';
    case TaskCancelled = 'task_cancelled';
    case HumanHandoffRequested = 'human_handoff_requested';
    case ContactDetailsCaptured = 'contact_details_captured';
    case IdentityMatched = 'identity_matched';
    case Deleted = 'deleted';
    case Restored = 'restored';

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
            self::TaskCreated => 'Follow-up task created',
            self::TaskStarted => 'Follow-up task started',
            self::TaskCompleted => 'Follow-up task completed',
            self::TaskCancelled => 'Follow-up task cancelled',
            self::HumanHandoffRequested => 'Human counsellor requested',
            self::ContactDetailsCaptured => 'Contact details captured',
            self::IdentityMatched => 'Lead matched from another source',
            self::Deleted => 'Lead deleted',
            self::Restored => 'Lead restored',
        };
    }
}
