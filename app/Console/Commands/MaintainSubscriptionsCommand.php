<?php

namespace App\Console\Commands;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionLifecycleService;
use App\Services\Billing\SubscriptionNotificationService;
use Illuminate\Console\Command;

class MaintainSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:maintain {--limit=100}';

    protected $description = 'Apply subscription expiry, grace-end, and cancel-at-period-end transitions';

    public function handle(
        SubscriptionLifecycleService $lifecycle,
        SubscriptionNotificationService $notifications,
    ): int {
        $limit = (int) $this->option('limit');
        $processed = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->limit($limit)
            ->get()
            ->each(function (Subscription $subscription) use ($lifecycle, &$processed): void {
                $lifecycle->expire($subscription, reason: 'Trial ended');
                $processed++;
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::Grace->value)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->limit($limit)
            ->get()
            ->each(function (Subscription $subscription) use ($lifecycle, &$processed): void {
                $lifecycle->markPastDue($subscription, reason: 'Grace period ended');
                $processed++;
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('cancel_at_period_end', true)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now())
            ->limit($limit)
            ->get()
            ->each(function (Subscription $subscription) use ($lifecycle, $notifications, &$processed): void {
                $lifecycle->applyPeriodEndCancellation($subscription, 'Period ended with cancel scheduled');
                $notifications->subscriptionExpired($subscription->tenant);
                $processed++;
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('cancel_at_period_end', false)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now())
            ->limit($limit)
            ->get()
            ->each(function (Subscription $subscription) use ($lifecycle, $notifications, &$processed): void {
                $lifecycle->expire($subscription, reason: 'Billing period ended');
                $notifications->subscriptionExpired($subscription->tenant);
                $processed++;
            });

        $this->info("Processed {$processed} subscription transitions.");

        return self::SUCCESS;
    }
}
