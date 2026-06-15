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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayPaymentProvider implements PaymentProviderContract
{
    public function __construct(
        private readonly PaymentCredentialsService $credentials,
    ) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Razorpay;
    }

    public function environment(): PaymentEnvironment
    {
        return $this->credentials->environment();
    }

    public function publicKeyId(): string
    {
        $keyId = $this->credentials->keyId(PaymentProvider::Razorpay, $this->environment());

        if (! is_string($keyId) || $keyId === '') {
            throw new PaymentException('Razorpay key ID is not configured.', PaymentFailureCategory::ProviderUnavailable);
        }

        return $keyId;
    }

    public function createOrder(ProviderOrderRequest $request): ProviderOrderResult
    {
        $keyId = $this->publicKeyId();
        $secret = $this->credentials->keySecret(PaymentProvider::Razorpay, $this->environment());

        if (! is_string($secret) || $secret === '') {
            throw new PaymentException('Razorpay key secret is not configured.', PaymentFailureCategory::ProviderUnavailable);
        }

        try {
            $response = Http::baseUrl((string) config('payments.providers.razorpay.base_url'))
                ->withBasicAuth($keyId, $secret)
                ->timeout((int) config('payments.request_timeout_seconds', 15))
                ->connectTimeout((int) config('payments.connect_timeout_seconds', 5))
                ->retry((int) config('payments.http_retries', 0), 0, throw: false)
                ->acceptJson()
                ->post('/orders', [
                    'amount' => $request->amountMinor,
                    'currency' => $request->currency,
                    'receipt' => $request->receiptReference,
                    'notes' => $request->notes,
                ]);
        } catch (ConnectionException) {
            throw new PaymentException('Razorpay request timed out.', PaymentFailureCategory::ProviderUnavailable);
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::warning('Razorpay authentication failed', ['status' => $response->status()]);

            throw new PaymentException('Razorpay authentication failed.', PaymentFailureCategory::ProviderUnavailable);
        }

        if ($response->status() === 429) {
            throw new PaymentException('Razorpay rate limit reached.', PaymentFailureCategory::ProviderUnavailable);
        }

        if (! $response->successful()) {
            Log::warning('Razorpay order creation failed', ['status' => $response->status()]);

            throw new PaymentException('Razorpay order creation failed.', PaymentFailureCategory::ProviderUnavailable);
        }

        $body = $response->json();

        if (! is_array($body) || empty($body['id'])) {
            throw new PaymentException('Razorpay returned an invalid response.', PaymentFailureCategory::ProviderUnavailable);
        }

        $amount = (int) ($body['amount'] ?? 0);
        $currency = (string) ($body['currency'] ?? '');

        if ($amount !== $request->amountMinor || strtoupper($currency) !== strtoupper($request->currency)) {
            throw new PaymentException('Razorpay order amount mismatch.', PaymentFailureCategory::AmountMismatch);
        }

        return new ProviderOrderResult(
            providerOrderId: (string) $body['id'],
            amountMinor: $amount,
            currency: strtoupper($currency),
            status: (string) ($body['status'] ?? 'created'),
            safeMetadata: [
                'status' => $body['status'] ?? null,
            ],
        );
    }

    public function verifyPaymentSignature(string $providerOrderId, string $providerPaymentId, string $signature): bool
    {
        $secret = $this->credentials->keySecret(PaymentProvider::Razorpay, $this->environment());

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $providerOrderId.'|'.$providerPaymentId, $secret);

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = $this->credentials->webhookSecret(PaymentProvider::Razorpay, $this->environment());

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function fetchOrder(string $providerOrderId): ?array
    {
        $keyId = $this->publicKeyId();
        $secret = $this->credentials->keySecret(PaymentProvider::Razorpay, $this->environment());

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        try {
            $response = Http::baseUrl((string) config('payments.providers.razorpay.base_url'))
                ->withBasicAuth($keyId, $secret)
                ->timeout((int) config('payments.request_timeout_seconds', 15))
                ->connectTimeout((int) config('payments.connect_timeout_seconds', 5))
                ->acceptJson()
                ->get('/orders/'.$providerOrderId);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }
}
