<?php

namespace App\Services\Billing;

use App\Enums\Billing\PlanFeature;
use App\Models\LeadNotification;
use App\Models\Subscription;
use App\Models\Tenant;

class SubscriptionNotificationService
{
    public function trialEnding(Tenant $tenant, Subscription $subscription): void
    {
        $this->notifyAdmins($tenant, 'trial_ending', 'Trial ending soon', 'Your trial ends on '.$subscription->trial_ends_at?->format('d M Y').'.');
    }

    public function subscriptionExpired(Tenant $tenant): void
    {
        $this->notifyAdmins($tenant, 'subscription_expired', 'Subscription expired', 'Your subscription has expired. Review your plan to restore access.');
    }

    public function graceStarted(Tenant $tenant, Subscription $subscription): void
    {
        $this->notifyAdmins($tenant, 'grace_started', 'Grace period started', 'Your subscription entered a grace period until '.$subscription->grace_ends_at?->format('d M Y').'.');
    }

    public function usageWarning(Tenant $tenant, PlanFeature $feature, int $threshold, int $used, int $limit): void
    {
        $this->notifyAdmins(
            $tenant,
            'usage_warning',
            'Usage at '.$threshold.'%',
            $feature->label().': '.$used.' of '.$limit.' used.',
        );
    }

    public function usageLimitReached(Tenant $tenant, PlanFeature $feature): void
    {
        $this->notifyAdmins($tenant, 'usage_limit_reached', 'Usage limit reached', $feature->label().' limit has been reached.');
    }

    private function notifyAdmins(Tenant $tenant, string $type, string $title, string $body): void
    {
        try {
            $admins = $tenant->memberships()
                ->whereIn('role', ['owner', 'admin'])
                ->where('status', 'active')
                ->with('user')
                ->get();

            foreach ($admins as $membership) {
                LeadNotification::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $membership->user_id,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                ]);
            }
        } catch (\Throwable) {
            // Notification failure must not break subscription transitions.
        }
    }
}
