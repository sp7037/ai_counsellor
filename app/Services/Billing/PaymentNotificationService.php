<?php

namespace App\Services\Billing;

use App\Models\LeadNotification;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use App\Models\Tenant;

class PaymentNotificationService
{
    public function paymentSuccessful(
        Tenant $tenant,
        PaymentOrder $order,
        Subscription $subscription,
        string $notificationKey,
    ): void {
        $this->notifyAdminsOnce($tenant, $notificationKey, 'payment_successful', 'Payment successful', sprintf(
            'Your payment for %s was successful. Subscription is active until %s.',
            $order->plan->name,
            $subscription->current_period_ends_at?->format('d M Y') ?? 'the current period end',
        ));
    }

    public function paymentFailed(Tenant $tenant, PaymentOrder $order, string $message, string $notificationKey): void
    {
        $this->notifyAdminsOnce($tenant, $notificationKey, 'payment_failed', 'Payment failed', $message);
    }

    private function notifyAdminsOnce(Tenant $tenant, string $key, string $type, string $title, string $body): void
    {
        try {
            if (LeadNotification::query()->where('tenant_id', $tenant->id)->where('type', $key)->exists()) {
                return;
            }

            $admins = $tenant->memberships()
                ->whereIn('role', ['owner', 'admin'])
                ->where('status', 'active')
                ->get();

            foreach ($admins as $membership) {
                LeadNotification::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $membership->user_id,
                    'type' => $key,
                    'title' => $title,
                    'body' => $body,
                ]);
            }
        } catch (\Throwable) {
            // Notification failure must not roll back payment finalization.
        }
    }
}
