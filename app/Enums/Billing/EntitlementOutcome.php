<?php

namespace App\Enums\Billing;

enum EntitlementOutcome: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';
    case AllowedWithWarning = 'allowed_with_warning';
    case LimitReached = 'limit_reached';
    case SubscriptionExpired = 'subscription_expired';
    case TenantSuspended = 'tenant_suspended';
    case FeatureNotIncluded = 'feature_not_included';
    case ConfigurationRequired = 'configuration_required';
    case NoSubscription = 'no_subscription';

    public function isAllowed(): bool
    {
        return in_array($this, [self::Allowed, self::AllowedWithWarning], true);
    }

    public function safeMessageForCounsellor(): string
    {
        return 'Your organisation\'s access to this feature is currently unavailable. Please contact your administrator.';
    }

    public function safeMessageForTenantAdmin(): string
    {
        return match ($this) {
            self::SubscriptionExpired, self::NoSubscription => 'Your subscription is not active. Review your plan on the subscription page.',
            self::TenantSuspended => 'This organisation is suspended. Contact platform support.',
            self::FeatureNotIncluded => 'This capability is not included in your current plan.',
            self::LimitReached => 'You have reached the usage limit for this capability.',
            self::ConfigurationRequired => 'Additional configuration is required before this feature can be used.',
            default => 'This action is not permitted under your current account status.',
        };
    }

    public function safeWidgetCode(): string
    {
        return match ($this) {
            self::TenantSuspended => 'service_unavailable',
            self::SubscriptionExpired, self::NoSubscription => 'service_unavailable',
            self::FeatureNotIncluded => 'feature_unavailable',
            self::LimitReached => 'limit_reached',
            default => 'unavailable',
        };
    }
}
