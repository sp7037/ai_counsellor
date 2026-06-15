<?php

namespace App\Services\Billing\Providers;

use App\Contracts\Billing\PaymentProviderContract;
use App\Data\Billing\ProviderOrderRequest;
use App\Data\Billing\ProviderOrderResult;
use App\Enums\Billing\PaymentEnvironment;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentProvider;
use App\Exceptions\Billing\PaymentException;
use App\Services\Billing\PaymentCredentialsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FakePaymentProvider implements PaymentProviderContract
{
    public function __construct(
        private readonly PaymentCredentialsService $credentials,
    ) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Fake;
    }

    public function environment(): PaymentEnvironment
    {
        return $this->credentials->environment();
    }

    public function publicKeyId(): string
    {
        return (string) ($this->credentials->keyId(PaymentProvider::Fake, $this->environment())
            ?? config('payments.providers.fake.key_id', 'rzp_test_fake'));
    }

    public function createOrder(ProviderOrderRequest $request): ProviderOrderResult
    {
        if (app()->environment('testing') && Http::getFacadeRoot() !== null) {
            // Respect Http::fake() when tests configure provider responses.
            $keyId = $this->publicKeyId();
            $secret = $this->credentials->keySecret(PaymentProvider::Fake, $this->environment())
                ?? config('payments.providers.fake.key_secret');

            $response = Http::baseUrl('https://fake-payments.test/v1')
                ->withBasicAuth($keyId, (string) $secret)
                ->acceptJson()
                ->post('/orders', [
                    'amount' => $request->amountMinor,
                    'currency' => $request->currency,
                    'receipt' => $request->receiptReference,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body) && ! empty($body['id'])) {
                    return new ProviderOrderResult(
                        providerOrderId: (string) $body['id'],
                        amountMinor: (int) ($body['amount'] ?? $request->amountMinor),
                        currency: strtoupper((string) ($body['currency'] ?? $request->currency)),
                        status: (string) ($body['status'] ?? 'created'),
                    );
                }
            }

            if (in_array($response->status(), [401, 403, 429, 500, 502, 503], true)) {
                throw new PaymentException('Fake provider HTTP failure.', PaymentFailureCategory::ProviderUnavailable);
            }
        }

        return new ProviderOrderResult(
            providerOrderId: 'order_'.Str::lower(Str::random(14)),
            amountMinor: $request->amountMinor,
            currency: strtoupper($request->currency),
            status: 'created',
        );
    }

    public function verifyPaymentSignature(string $providerOrderId, string $providerPaymentId, string $signature): bool
    {
        $secret = $this->credentials->keySecret(PaymentProvider::Fake, $this->environment())
            ?? config('payments.providers.fake.key_secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $providerOrderId.'|'.$providerPaymentId, $secret);

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = $this->credentials->webhookSecret(PaymentProvider::Fake, $this->environment())
            ?? config('payments.providers.fake.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function fetchOrder(string $providerOrderId): ?array
    {
        return [
            'id' => $providerOrderId,
            'status' => 'paid',
        ];
    }
}
