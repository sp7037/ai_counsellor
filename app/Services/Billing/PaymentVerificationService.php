<?php

namespace App\Services\Billing;

use App\Enums\Billing\PaymentEventType;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentOrderStatus;
use App\Exceptions\Billing\PaymentException;
use App\Models\PaymentOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentVerificationService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly BillingService $billing,
        private readonly PaymentEventRecorder $events,
    ) {}

    /**
     * @return array{success: bool, order: PaymentOrder, message?: string}
     */
    public function verifyBrowserPayment(
        Tenant $tenant,
        User $actor,
        string $providerOrderId,
        string $providerPaymentId,
        string $signature,
    ): array {
        if (! $actor->tenantRoleFor($tenant)?->canManageBilling()) {
            throw ValidationException::withMessages(['tenant' => 'You are not authorised to verify this payment.']);
        }

        $order = PaymentOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider_order_id', $providerOrderId)
            ->first();

        if ($order === null) {
            throw ValidationException::withMessages(['order' => 'Payment order not found.']);
        }

        if ($order->provider_order_id !== $providerOrderId) {
            throw new PaymentException('Order mismatch.', PaymentFailureCategory::Unknown);
        }

        $provider = $this->providers->resolve($order->provider);

        if (! $provider->verifyPaymentSignature($providerOrderId, $providerPaymentId, $signature)) {
            $this->events->record($order, PaymentEventType::VerificationRejected, 'browser', [
                'failure_category' => PaymentFailureCategory::SignatureInvalid->value,
            ]);

            throw new PaymentException('Invalid payment signature.', PaymentFailureCategory::SignatureInvalid);
        }

        $this->events->record($order, PaymentEventType::PaymentVerified, 'browser', [
            'provider_payment_id' => $providerPaymentId,
        ]);

        try {
            $result = $this->billing->finalizeVerifiedPayment(
                order: $order,
                providerPaymentId: $providerPaymentId,
                amountMinor: (int) $order->amount_minor,
                currency: (string) $order->currency,
                verificationSource: 'browser',
                actor: $actor,
            );
        } catch (PaymentException $exception) {
            if ($order->status !== PaymentOrderStatus::Paid) {
                $order->update([
                    'status' => PaymentOrderStatus::Failed,
                    'failed_at' => now(),
                ]);
            }

            throw $exception;
        }

        return [
            'success' => true,
            'order' => $result->order,
            'already_finalized' => $result->wasAlreadyFinalized,
        ];
    }
}
