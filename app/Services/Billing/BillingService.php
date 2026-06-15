<?php

namespace App\Services\Billing;

use App\Data\Billing\PaymentFinalizationResult;
use App\Enums\Billing\PaymentEventType;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentOrderStatus;
use App\Enums\Billing\PaymentStatus;
use App\Exceptions\Billing\PaymentException;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly SubscriptionLifecycleService $subscriptions,
        private readonly PaymentEventRecorder $events,
        private readonly PaymentNotificationService $notifications,
    ) {}

    public function finalizeVerifiedPayment(
        PaymentOrder $order,
        string $providerPaymentId,
        int $amountMinor,
        string $currency,
        string $verificationSource,
        ?User $actor = null,
        ?string $paymentMethodCategory = null,
        array $safeMetadata = [],
    ): PaymentFinalizationResult {
        return DB::transaction(function () use (
            $order,
            $providerPaymentId,
            $amountMinor,
            $currency,
            $verificationSource,
            $actor,
            $paymentMethodCategory,
            $safeMetadata,
        ): PaymentFinalizationResult {
            $lockedOrder = PaymentOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->with(['tenant', 'plan'])
                ->firstOrFail();

            $existingPayment = Payment::query()
                ->where('provider', $lockedOrder->provider)
                ->where('provider_mode', $lockedOrder->provider_mode)
                ->where('provider_payment_id', $providerPaymentId)
                ->first();

            if ($lockedOrder->subscription_activation_completed_at !== null) {
                $payment = $existingPayment ?? $lockedOrder->successfulPayment()->firstOrFail();

                return new PaymentFinalizationResult(
                    order: $lockedOrder,
                    payment: $payment,
                    subscription: $lockedOrder->activatedSubscription,
                    wasAlreadyFinalized: true,
                );
            }

            if (strtoupper($currency) !== strtoupper((string) $lockedOrder->currency)) {
                throw new PaymentException('Currency mismatch.', PaymentFailureCategory::CurrencyMismatch);
            }

            if ($amountMinor !== (int) $lockedOrder->amount_minor) {
                throw new PaymentException('Amount mismatch.', PaymentFailureCategory::AmountMismatch);
            }

            if ($existingPayment !== null && $existingPayment->status === PaymentStatus::Captured) {
                $lockedOrder->update([
                    'status' => PaymentOrderStatus::Paid,
                    'paid_at' => $lockedOrder->paid_at ?? now(),
                ]);
            }

            $payment = $existingPayment ?? Payment::query()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'payment_order_id' => $lockedOrder->id,
                'provider' => $lockedOrder->provider,
                'provider_mode' => $lockedOrder->provider_mode,
                'provider_payment_id' => $providerPaymentId,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
                'status' => PaymentStatus::Captured,
                'payment_method_category' => $paymentMethodCategory,
                'verified_at' => now(),
                'captured_at' => now(),
                'metadata' => $safeMetadata === [] ? null : $safeMetadata,
            ]);

            if ($payment->status !== PaymentStatus::Captured) {
                throw new PaymentException('Payment is not captured.', PaymentFailureCategory::PaymentDeclined);
            }

            $lockedOrder->update([
                'status' => PaymentOrderStatus::Paid,
                'paid_at' => $lockedOrder->paid_at ?? now(),
            ]);

            $this->events->record($lockedOrder, PaymentEventType::PaymentCaptured, $verificationSource, [
                'provider_payment_id' => $providerPaymentId,
            ], $payment);

            $this->events->record($lockedOrder, PaymentEventType::SubscriptionActivationRequested, $verificationSource, [], $payment);

            $subscription = $this->subscriptions->applyVerifiedPayment(
                $lockedOrder->tenant,
                $lockedOrder->plan,
                $lockedOrder,
                $actor,
            );

            $notificationKey = 'payment:'.$lockedOrder->uuid.':success';

            $lockedOrder->update([
                'subscription_id' => $subscription->id,
                'activated_subscription_id' => $subscription->id,
                'subscription_activation_completed_at' => now(),
                'notification_key' => $notificationKey,
            ]);

            $this->events->record($lockedOrder, PaymentEventType::SubscriptionActivationCompleted, $verificationSource, [
                'subscription_uuid' => $subscription->uuid,
            ], $payment);

            $this->notifications->paymentSuccessful($lockedOrder->tenant, $lockedOrder, $subscription, $notificationKey);

            return new PaymentFinalizationResult(
                order: $lockedOrder->fresh(['plan']),
                payment: $payment,
                subscription: $subscription,
                wasAlreadyFinalized: false,
            );
        });
    }
}
