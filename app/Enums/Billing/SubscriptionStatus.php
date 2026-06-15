<?php

namespace App\Enums\Billing;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Grace = 'grace';
    case PastDue = 'past_due';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Grace => 'Grace period',
            self::PastDue => 'Past due',
            self::Cancelled => 'Cancelled',
        };
    }

    public function allowsOperationalAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::Grace], true);
    }

    public function allowsWidgetAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::Grace], true);
    }

    public function allowsAiResponses(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::Grace], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }
}
