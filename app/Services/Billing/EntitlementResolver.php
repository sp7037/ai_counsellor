<?php

namespace App\Services\Billing;

use App\Data\Billing\EntitlementResult;
use App\Enums\Billing\EntitlementOutcome;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Tenancy\TenantStatus;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Models\PlanEntitlement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;

class EntitlementResolver
{
    /** @var array<string, EntitlementResult> */
    private array $cache = [];

    public function __construct(
        private readonly UsageTrackingService $usage,
    ) {}

    public function clearCache(): void
    {
        $this->cache = [];
    }

    public function check(Tenant $tenant, PlanFeature $feature): EntitlementResult
    {
        $cacheKey = $tenant->id.':'.$feature->value;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = $this->resolve($tenant, $feature);
        $this->cache[$cacheKey] = $result;

        return $result;
    }

    public function assertAllowed(Tenant $tenant, PlanFeature $feature): void
    {
        $result = $this->check($tenant, $feature);

        if (! $result->isAllowed()) {
            throw new EntitlementDeniedException($result);
        }
    }

    public function featureLimit(Tenant $tenant, PlanFeature $feature): ?int
    {
        $config = $this->featureConfig($tenant, $feature);

        if ($config === null || ! $config['enabled']) {
            return 0;
        }

        return $config['limit'];
    }

    public function subscriptionFor(Tenant $tenant): ?Subscription
    {
        return $tenant->relationLoaded('subscription')
            ? $tenant->subscription
            : $tenant->subscription()->with('plan.entitlements')->first();
    }

    public function effectiveSubscriptionStatus(Tenant $tenant): ?SubscriptionStatus
    {
        $subscription = $this->subscriptionFor($tenant);

        return $subscription?->effectiveStatus();
    }

    public function usageSummary(Tenant $tenant): array
    {
        $subscription = $this->subscriptionFor($tenant);

        if ($subscription === null) {
            return [];
        }

        $summary = [];

        foreach (PlanFeature::cases() as $feature) {
            $metric = $feature->limitMetric();

            if ($metric === null) {
                continue;
            }

            $limit = $this->featureLimit($tenant, $feature);

            if ($limit === null) {
                continue;
            }

            $used = $this->usage->getUsed($tenant, $metric, $subscription);
            $summary[$feature->value] = [
                'label' => $feature->label(),
                'used' => $used,
                'limit' => $limit,
                'unlimited' => false,
            ];
        }

        return $summary;
    }

    private function resolve(Tenant $tenant, PlanFeature $feature): EntitlementResult
    {
        if (! $tenant->allowsTenantAccess()) {
            return $this->resultForInactiveTenant($tenant, $feature);
        }

        $subscription = $this->subscriptionFor($tenant);

        if ($subscription === null) {
            return new EntitlementResult(
                outcome: EntitlementOutcome::NoSubscription,
                feature: $feature,
            );
        }

        $effectiveStatus = $subscription->effectiveStatus();

        if ($effectiveStatus->isTerminal() || $effectiveStatus === SubscriptionStatus::PastDue) {
            if ($feature === PlanFeature::Widget && $effectiveStatus === SubscriptionStatus::Expired) {
                return $this->widgetExpiredResult($tenant, $feature, $subscription);
            }

            if (! $effectiveStatus->allowsOperationalAccess()) {
                return new EntitlementResult(
                    outcome: EntitlementOutcome::SubscriptionExpired,
                    feature: $feature,
                );
            }
        }

        $config = $this->featureConfig($tenant, $feature, $subscription);

        if ($config === null || ! $config['enabled']) {
            return new EntitlementResult(
                outcome: EntitlementOutcome::FeatureNotIncluded,
                feature: $feature,
            );
        }

        $metric = $feature->limitMetric();
        $limit = $config['limit'];
        $used = null;
        $warningPercent = null;

        if ($metric !== null && $limit !== null) {
            $used = $this->usage->getUsed($tenant, $metric, $subscription);

            if ($used >= $limit) {
                return new EntitlementResult(
                    outcome: EntitlementOutcome::LimitReached,
                    feature: $feature,
                    limit: $limit,
                    used: $used,
                );
            }

            $percent = $limit > 0 ? (int) floor(($used / $limit) * 100) : 0;
            $thresholds = config('subscriptions.usage_warning_thresholds', [75, 90]);

            foreach ($thresholds as $threshold) {
                if ($percent >= $threshold) {
                    $warningPercent = $threshold;
                    break;
                }
            }

            if ($warningPercent !== null) {
                return new EntitlementResult(
                    outcome: EntitlementOutcome::AllowedWithWarning,
                    feature: $feature,
                    limit: $limit,
                    used: $used,
                    warningThresholdPercent: $warningPercent,
                );
            }
        }

        if ($effectiveStatus === SubscriptionStatus::Grace) {
            return new EntitlementResult(
                outcome: EntitlementOutcome::AllowedWithWarning,
                feature: $feature,
                limit: $limit,
                used: $used,
                message: 'Your subscription is in a grace period. Please renew soon.',
            );
        }

        return new EntitlementResult(
            outcome: EntitlementOutcome::Allowed,
            feature: $feature,
            limit: $limit,
            used: $used,
        );
    }

    private function widgetExpiredResult(Tenant $tenant, PlanFeature $feature, Subscription $subscription): EntitlementResult
    {
        $leadCapture = $this->featureConfig($tenant, PlanFeature::LeadManagement, $subscription);

        if ($leadCapture !== null && $leadCapture['enabled'] && $feature === PlanFeature::Widget) {
            return new EntitlementResult(
                outcome: EntitlementOutcome::Allowed,
                feature: $feature,
                message: 'lead_capture_only',
            );
        }

        return new EntitlementResult(
            outcome: EntitlementOutcome::SubscriptionExpired,
            feature: $feature,
        );
    }

    /**
     * @return array{enabled: bool, limit: ?int}|null
     */
    private function featureConfig(Tenant $tenant, PlanFeature $feature, ?Subscription $subscription = null): ?array
    {
        $subscription ??= $this->subscriptionFor($tenant);

        if ($subscription === null) {
            return null;
        }

        $override = $this->activeOverride($tenant, $feature);

        if ($override !== null) {
            if ($override->enabled === false) {
                return ['enabled' => false, 'limit' => 0];
            }

            $planLimit = $this->planFeatureLimit($subscription, $feature);

            return [
                'enabled' => true,
                'limit' => $override->limit_value ?? $planLimit,
            ];
        }

        $entitlement = $this->planEntitlement($subscription, $feature);

        if ($entitlement === null) {
            return ['enabled' => false, 'limit' => 0];
        }

        return [
            'enabled' => $entitlement->enabled,
            'limit' => $entitlement->isUnlimited() ? null : $entitlement->limit_value,
        ];
    }

    private function activeOverride(Tenant $tenant, PlanFeature $feature): ?TenantEntitlementOverride
    {
        return TenantEntitlementOverride::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature', $feature->value)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    private function planEntitlement(Subscription $subscription, PlanFeature $feature): ?PlanEntitlement
    {
        $plan = $subscription->relationLoaded('plan')
            ? $subscription->plan
            : $subscription->plan()->with('entitlements')->first();

        if ($plan === null) {
            return null;
        }

        $entitlements = $plan->relationLoaded('entitlements')
            ? $plan->entitlements
            : $plan->entitlements()->get();

        return $entitlements->first(fn (PlanEntitlement $e) => $e->feature === $feature);
    }

    private function planFeatureLimit(Subscription $subscription, PlanFeature $feature): ?int
    {
        $entitlement = $this->planEntitlement($subscription, $feature);

        if ($entitlement === null || $entitlement->isUnlimited()) {
            return null;
        }

        return $entitlement->limit_value;
    }

    private function resultForInactiveTenant(Tenant $tenant, PlanFeature $feature): EntitlementResult
    {
        return match ($tenant->status) {
            TenantStatus::Pending => new EntitlementResult(
                outcome: EntitlementOutcome::ConfigurationRequired,
                feature: $feature,
                message: 'Your organisation account is pending activation. Contact the SR Worlds platform administrator to activate your account and assign a plan. You can add counsellors only after activation is complete.',
            ),
            TenantStatus::Suspended => new EntitlementResult(
                outcome: EntitlementOutcome::TenantSuspended,
                feature: $feature,
                message: EntitlementOutcome::TenantSuspended->safeMessageForTenantAdmin(),
            ),
            TenantStatus::Archived => new EntitlementResult(
                outcome: EntitlementOutcome::Denied,
                feature: $feature,
                message: 'This organisation has been archived. Contact platform support to restore it.',
            ),
            TenantStatus::Deleted => new EntitlementResult(
                outcome: EntitlementOutcome::Denied,
                feature: $feature,
                message: 'This organisation has been deleted.',
            ),
            TenantStatus::Cancelled => new EntitlementResult(
                outcome: EntitlementOutcome::SubscriptionExpired,
                feature: $feature,
                message: 'This organisation has been cancelled.',
            ),
            default => new EntitlementResult(
                outcome: EntitlementOutcome::Denied,
                feature: $feature,
                message: 'This organisation is not currently active.',
            ),
        };
    }
}
