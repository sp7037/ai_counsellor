<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\Billing\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\Tenant;
use App\Services\Billing\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentVerificationController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, PaymentVerificationService $verification): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $verification->verifyBrowserPayment(
                tenant: $tenant,
                actor: $request->user(),
                providerOrderId: $validated['razorpay_order_id'],
                providerPaymentId: $validated['razorpay_payment_id'],
                signature: $validated['razorpay_signature'],
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception instanceof PaymentException
                    ? $exception->category->safeMessage()
                    : 'Payment verification failed.',
            ], 422);
        }

        /** @var PaymentOrder $order */
        $order = $result['order'];

        return response()->json([
            'success' => true,
            'order_uuid' => $order->uuid,
            'redirect' => route('tenant.subscription.payment.success', [$tenant, $order]),
        ]);
    }
}
