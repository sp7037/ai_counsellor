<?php

namespace App\Enums\Billing;

enum SubscriptionEventType: string
{
    case Created = 'created';
    case TrialStarted = 'trial_started';
    case TrialExtended = 'trial_extended';
    case Activated = 'activated';
    case GraceStarted = 'grace_started';
    case GraceRecovered = 'grace_recovered';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case CancelScheduled = 'cancel_scheduled';
    case Cancelled = 'cancelled';
    case Restored = 'restored';
    case PlanChanged = 'plan_changed';
    case OverrideApplied = 'override_applied';
    case OverrideRemoved = 'override_removed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Subscription created',
            self::TrialStarted => 'Trial started',
            self::TrialExtended => 'Trial extended',
            self::Activated => 'Subscription activated',
            self::GraceStarted => 'Grace period started',
            self::GraceRecovered => 'Recovered from grace',
            self::PastDue => 'Marked past due',
            self::Expired => 'Subscription expired',
            self::CancelScheduled => 'Cancellation scheduled',
            self::Cancelled => 'Subscription cancelled',
            self::Restored => 'Subscription restored',
            self::PlanChanged => 'Plan changed',
            self::OverrideApplied => 'Entitlement override applied',
            self::OverrideRemoved => 'Entitlement override removed',
        };
    }
}
