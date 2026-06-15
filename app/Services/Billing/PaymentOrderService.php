<?php

namespace App\Services\Billing;

use App\Data\Billing\ProviderOrderRequest;
use App\Enums\Billing\PaymentEventType;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentOrderStatus;
use App\Enums\Billing\PlanStatus;
use App\Exceptions\Billing\PaymentException;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentOrderService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentCredentialsService $credentials,
        private readonly PaymentEventRecorder $events,
    ) {}

    /**
     * @return array{order: PaymentOrder, provider_key_id: string, reused: bool}
     */
    public function createCheckoutOrder(
        Tenant $tenant,
        Plan $plan,
        User $actor,
        string $checkoutRequestUuid,
    ): array {
        if (! $actor->tenantRoleFor($tenant)?->canManageBilling()) {
            throw ValidationException::withMessages(['tenant' => 'You are not authorised to purchase for this tenant.']);
        }

        if (! $plan->isPurchasable()) {
            throw ValidationException::withMessages(['plan' => 'This plan is not available for purchase.']);
        }

        if ($plan->status !== PlanStatus::Active) {
            throw ValidationException::withMessages(['plan' => 'This plan is not active.']);
        }

        if (! $this->credentials->isEnabled()) {
            throw ValidationException::withMessages(['payment' => 'Online payments are not enabled.']);
        }

        $existing = PaymentOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('checkout_request_uuid', $checkoutRequestUuid)
            ->first();

        if ($existing !== null) {
            if ($existing->isOpen() && ($existing->expires_at === null || $existing->expires_at->isFuture())) {
                $provider = $this->providers->resolve($existing->provider);

                return [
                    'order' => $existing->load('plan'),
                    'provider_key_id' => $provider->publicKeyId(),
                    'reused' => true,
                ];
            }

            if ($existing->isPaid()) {
                throw ValidationException::withMessages(['checkout' => 'This checkout request has already been paid.']);
            }
        }

        return DB::transaction(function () use ($tenant, $plan, $actor, $checkoutRequestUuid): array {
            $provider = $this->providers->resolve();
            $amountMinor = (int) $plan->amount_minor + (int) ($plan->setup_fee_minor ?? 0);
            $currency = strtoupper((string) $plan->currency);
            $reference = 'po_'.Str::lower(Str::random(20));

            $order = PaymentOrder::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'checkout_request_uuid' => $checkoutRequestUuid,
                'provider' => $provider->provider(),
                'provider_mode' => $provider->environment(),
                'internal_reference' => $reference,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => PaymentOrderStatus::Pending,
                'description' => 'Subscription: '.$plan->name,
                'receipt_reference' => $reference,
                'initiated_by' => $actor->id,
                'expires_at' => now()->addMinutes((int) config('payments.order_expiry_minutes', 30)),
            ]);

            $this->events->record($order, PaymentEventType::OrderCreated, 'system', [
                'plan_id' => $plan->id,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
            ]);

            try {
                $providerResult = $provider->createOrder(new ProviderOrderRequest(
                    amountMinor: $amountMinor,
                    currency: $currency,
                    receiptReference: $reference,
                    description: $order->description ?? $plan->name,
                    notes: [
                        'tenant_uuid' => $tenant->uuid,
                        'plan_code' => $plan->code,
                        'internal_reference' => $reference,
                    ],
                ));
            } catch (PaymentException $exception) {
                $order->update([
                    'status' => PaymentOrderStatus::Failed,
                    'failed_at' => now(),
                    'metadata' => ['failure_category' => $exception->category->value],
                ]);
                $this->events->record($order, PaymentEventType::PaymentFailed, 'system', [
                    'failure_category' => $exception->category->value,
                ]);

                throw $exception;
            }

            if ($providerResult->amountMinor !== $amountMinor || strtoupper($providerResult->currency) !== $currency) {
                $order->update([
                    'status' => PaymentOrderStatus::Failed,
                    'failed_at' => now(),
                ]);

                throw new PaymentException('Provider order amount mismatch.', PaymentFailureCategory::AmountMismatch);
            }

            $order->update([
                'provider_order_id' => $providerResult->providerOrderId,
                'status' => PaymentOrderStatus::Created,
                'metadata' => $providerResult->safeMetadata,
            ]);

            $this->events->record($order, PaymentEventType::CheckoutInitiated, 'browser', [
                'provider_order_id' => $providerResult->providerOrderId,
            ]);

            return [
                'order' => $order->fresh(['plan']),
                'provider_key_id' => $provider->publicKeyId(),
                'reused' => false,
            ];
        });
    }

    public function expireStaleOrders(int $batchSize = 50): int
    {
        $count = 0;
        $orders = PaymentOrder::query()
            ->whereIn('status', [PaymentOrderStatus::Pending->value, PaymentOrderStatus::Created->value])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->limit($batchSize)
            ->get();

        foreach ($orders as $order) {
            if ($order->status === PaymentOrderStatus::Paid) {
                continue;
            }

            $order->update([
                'status' => PaymentOrderStatus::Expired,
            ]);
            $this->events->record($order, PaymentEventType::OrderExpired, 'system');
            $count++;
        }

        return $count;
    }
}
