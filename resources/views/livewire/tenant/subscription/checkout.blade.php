<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Billing\PaymentCredentialsService;
use App\Services\Billing\PaymentOrderService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public Plan $plan;

    public string $checkoutRequestUuid;

    public bool $processing = false;

    public ?string $errorMessage = null;

    public function mount(Tenant $tenant, Plan $plan): void
    {
        $this->tenant = $tenant;
        $this->plan = $plan;
        $this->checkoutRequestUuid = (string) session('checkout_request_uuid', Str::uuid());
        session(['checkout_request_uuid' => $this->checkoutRequestUuid]);
    }

    public function with(PaymentCredentialsService $credentials): array
    {
        return [
            'testMode' => $credentials->environment()->value === 'test',
            'paymentsEnabled' => $credentials->isEnabled(),
        ];
    }

    public function startCheckout(PaymentOrderService $orders, PaymentCredentialsService $credentials): void
    {
        if ($this->processing) {
            return;
        }

        $this->processing = true;
        $this->errorMessage = null;

        try {
            $result = $orders->createCheckoutOrder(
                $this->tenant,
                $this->plan,
                auth()->user(),
                $this->checkoutRequestUuid,
            );

            $order = $result['order'];

            if ($credentials->activeProvider() === \App\Enums\Billing\PaymentProvider::Fake) {
                $this->completeFakePayment($order, $credentials);

                return;
            }

            $this->dispatch('checkout-ready', orderUuid: $order->uuid, providerOrderId: $order->provider_order_id, keyId: $result['provider_key_id'], amount: $order->amount_minor, currency: $order->currency, tenantName: $this->tenant->name, planName: $this->plan->name);
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception instanceof \App\Exceptions\Billing\PaymentException
                ? $exception->category->safeMessage()
                : ($exception->getMessage() ?: 'Unable to start checkout.');
        } finally {
            $this->processing = false;
        }
    }

    private function completeFakePayment(\App\Models\PaymentOrder $order, PaymentCredentialsService $credentials): void
    {
        $paymentId = 'pay_fake_'.str()->lower(str()->random(12));
        $secret = $credentials->keySecret(\App\Enums\Billing\PaymentProvider::Fake, $credentials->environment())
            ?? config('payments.providers.fake.key_secret');
        $signature = hash_hmac('sha256', $order->provider_order_id.'|'.$paymentId, (string) $secret);

        app(\App\Services\Billing\PaymentVerificationService::class)->verifyBrowserPayment(
            tenant: $this->tenant,
            actor: auth()->user(),
            providerOrderId: (string) $order->provider_order_id,
            providerPaymentId: $paymentId,
            signature: $signature,
        );

        session()->forget('checkout_request_uuid');
        $this->redirect(route('tenant.subscription.payment.success', [$this->tenant, $order]), navigate: true);
    }
}; ?>

<x-slot:heading>Checkout</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.subscription.plans', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="mx-auto grid max-w-lg gap-6">
    @if ($testMode)
        <flux:callout variant="warning">Test mode — use provider test credentials only.</flux:callout>
    @endif

    <flux:card class="grid gap-4">
        <flux:heading size="lg">{{ $plan->name }}</flux:heading>
        @if ($plan->isPurchasable())
            <p class="text-2xl font-semibold">{{ $plan->formattedPrice() }}</p>
            <flux:text class="text-zinc-400">Billed {{ $plan->billing_interval }}. Amount is determined by the server.</flux:text>
        @else
            <flux:callout variant="danger">This plan cannot be purchased online.</flux:callout>
        @endif

        @if ($errorMessage)
            <flux:callout variant="danger">{{ $errorMessage }}</flux:callout>
        @endif

        @if ($plan->isPurchasable() && $paymentsEnabled)
            <flux:button wire:click="startCheckout" wire:loading.attr="disabled" wire:target="startCheckout" :disabled="$processing">
                <span wire:loading.remove wire:target="startCheckout">Pay securely</span>
                <span wire:loading wire:target="startCheckout">Preparing checkout…</span>
            </flux:button>
        @endif
    </flux:card>
</div>

@script
<script>
    $wire.on('checkout-ready', async (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;

        if (typeof Razorpay === 'undefined') {
            await loadScript('https://checkout.razorpay.com/v1/checkout.js');
        }

        const failedBase = @js(url('/app/'.$tenant->uuid.'/subscription/payment'));

        const options = {
            key: data.keyId,
            amount: data.amount,
            currency: data.currency,
            name: @js(config('payments.platform_legal_name')),
            description: data.planName,
            order_id: data.providerOrderId,
            handler: async function (response) {
                await verifyPayment(data, response.razorpay_payment_id, response.razorpay_signature);
            },
            modal: {
                ondismiss: function () {
                    window.location.href = failedBase + '/' + data.orderUuid + '/failed?reason=cancelled';
                }
            },
            theme: { color: '#0ea5e9' }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    });

    async function verifyPayment(data, paymentId, signature) {
        const response = await fetch(@js(route('tenant.subscription.payments.verify', $tenant)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @js(csrf_token()),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                razorpay_order_id: data.providerOrderId,
                razorpay_payment_id: paymentId,
                razorpay_signature: signature,
            }),
        });

        const body = await response.json();
        if (response.ok && body.success && body.redirect) {
            window.location.href = body.redirect;
            return;
        }

        const failedBase = @js(url('/app/'.$tenant->uuid.'/subscription/payment'));
        window.location.href = failedBase + '/' + data.orderUuid + '/failed?reason=verification';
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
</script>
@endscript
