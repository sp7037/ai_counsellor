<?php

namespace App\Services\Billing;

use App\Enums\Billing\PaymentEventType;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentOrderStatus;
use App\Enums\Billing\PaymentProvider;
use App\Exceptions\Billing\PaymentException;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentCredentialsService $credentials,
        private readonly BillingService $billing,
        private readonly PaymentEventRecorder $events,
    ) {}

    public function handle(
        PaymentProvider $provider,
        string $rawBody,
        ?string $signature,
    ): array {
        if ($signature === null || trim($signature) === '') {
            throw new PaymentException('Missing webhook signature.', PaymentFailureCategory::SignatureInvalid);
        }

        $paymentProvider = $this->providers->resolve($provider);

        if (! $paymentProvider->verifyWebhookSignature($rawBody, $signature)) {
            throw new PaymentException('Invalid webhook signature.', PaymentFailureCategory::SignatureInvalid);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new PaymentException('Invalid webhook payload.', PaymentFailureCategory::Unknown);
        }

        $eventId = (string) ($payload['id'] ?? hash('sha256', $rawBody));
        $eventType = (string) ($payload['event'] ?? 'unknown');
        $environment = $this->credentials->environment();

        $existing = PaymentWebhookEvent::query()
            ->where('provider', $provider->value)
            ->where('provider_mode', $environment->value)
            ->where('provider_event_id', $eventId)
            ->first();

        if ($existing !== null) {
            return ['status' => 'duplicate', 'event_id' => $eventId];
        }

        $webhookEvent = PaymentWebhookEvent::query()->create([
            'provider' => $provider->value,
            'provider_mode' => $environment->value,
            'provider_event_id' => $eventId,
            'event_type' => $eventType,
            'status' => 'received',
            'event_hash' => hash('sha256', $rawBody),
            'metadata' => $this->redactPayload($payload),
            'created_at' => now(),
        ]);

        try {
            $result = $this->processEvent($payload, $provider, $webhookEvent);
            $webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return array_merge(['status' => 'processed', 'event_id' => $eventId], $result);
        } catch (\Throwable $exception) {
            Log::warning('Payment webhook processing failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'message' => $exception->getMessage(),
            ]);

            $webhookEvent->update([
                'status' => 'failed',
                'processed_at' => now(),
                'metadata' => array_merge($webhookEvent->metadata ?? [], [
                    'error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function processEvent(array $payload, PaymentProvider $provider, PaymentWebhookEvent $webhookEvent): array
    {
        $eventType = (string) ($payload['event'] ?? '');

        if (! in_array($eventType, ['payment.captured', 'order.paid'], true)) {
            return ['handled' => false, 'reason' => 'unsupported_event'];
        }

        $entity = $payload['payload']['payment']['entity']
            ?? $payload['payload']['order']['entity']
            ?? null;

        if (! is_array($entity)) {
            return ['handled' => false, 'reason' => 'missing_entity'];
        }

        $providerOrderId = (string) ($entity['order_id'] ?? $entity['id'] ?? '');
        $providerPaymentId = (string) ($entity['id'] ?? '');
        $amountMinor = (int) ($entity['amount'] ?? 0);
        $currency = strtoupper((string) ($entity['currency'] ?? ''));

        if ($providerOrderId === '' || $providerPaymentId === '') {
            return ['handled' => false, 'reason' => 'missing_ids'];
        }

        $order = PaymentOrder::query()
            ->where('provider', $provider->value)
            ->where('provider_order_id', $providerOrderId)
            ->first();

        if ($order === null) {
            return ['handled' => false, 'reason' => 'unknown_order'];
        }

        if ($order->provider_mode->value !== $this->credentials->environment()->value) {
            throw new PaymentException('Webhook mode mismatch.', PaymentFailureCategory::ModeMismatch);
        }

        $this->events->record($order, PaymentEventType::WebhookReceived, 'webhook', [
            'event_type' => $eventType,
            'webhook_event_id' => $webhookEvent->uuid,
        ]);
        $this->events->record($order, PaymentEventType::WebhookVerified, 'webhook', [
            'event_type' => $eventType,
        ]);

        if ($eventType === 'payment.failed') {
            if ($order->status !== PaymentOrderStatus::Paid) {
                $order->update([
                    'status' => PaymentOrderStatus::Failed,
                    'failed_at' => now(),
                ]);
            }

            return ['handled' => true, 'action' => 'failed_recorded'];
        }

        if ($order->status === PaymentOrderStatus::Paid && $order->subscription_activation_completed_at !== null) {
            return ['handled' => true, 'action' => 'already_finalized'];
        }

        $result = $this->billing->finalizeVerifiedPayment(
            order: $order,
            providerPaymentId: $providerPaymentId,
            amountMinor: $amountMinor,
            currency: $currency,
            verificationSource: 'webhook',
            paymentMethodCategory: isset($entity['method']) ? (string) $entity['method'] : null,
            safeMetadata: [
                'event_type' => $eventType,
            ],
        );

        return [
            'handled' => true,
            'action' => $result->wasAlreadyFinalized ? 'already_finalized' : 'finalized',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        return [
            'event' => $payload['event'] ?? null,
            'id' => $payload['id'] ?? null,
            'entity' => $payload['entity'] ?? null,
            'contains' => array_keys($payload['payload'] ?? []),
        ];
    }
}
