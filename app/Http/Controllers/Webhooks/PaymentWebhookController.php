<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Billing\PaymentProvider;
use App\Exceptions\Billing\PaymentException;
use App\Services\Billing\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController
{
    public function __invoke(Request $request, string $provider, PaymentWebhookService $webhooks): Response
    {
        if ($provider !== PaymentProvider::Razorpay->value && $provider !== PaymentProvider::Fake->value) {
            return response('Not found', 404);
        }

        $rawBody = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        try {
            $result = $webhooks->handle(
                PaymentProvider::from($provider === PaymentProvider::Fake->value ? 'fake' : 'razorpay'),
                $rawBody,
                is_string($signature) ? $signature : null,
            );
        } catch (PaymentException) {
            return response('Invalid signature', 400);
        } catch (\Throwable) {
            return response('Processing failed', 500);
        }

        return response(json_encode($result), 200, ['Content-Type' => 'application/json']);
    }
}
