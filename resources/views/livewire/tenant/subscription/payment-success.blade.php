<?php

use App\Models\PaymentOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public PaymentOrder $order;

    public function mount(Tenant $tenant, PaymentOrder $order): void
    {
        Gate::authorize('view', $order);
        abort_unless($order->tenant_id === $tenant->id, 404);

        $this->tenant = $tenant;
        $this->order = $order->load(['plan', 'successfulPayment']);
    }
}; ?>

<x-slot:heading>Payment successful</x-slot:heading>

<div class="mx-auto max-w-lg">
    <flux:card class="grid gap-4 text-center">
        <flux:heading size="lg">Thank you</flux:heading>
        <flux:text>Your payment for <strong>{{ $order->plan->name }}</strong> was verified successfully.</flux:text>
        <flux:text class="text-zinc-400">Reference: {{ $order->internal_reference }}</flux:text>
        @if ($order->successfulPayment)
            <flux:button href="{{ route('tenant.subscription.payment.receipt', [$tenant, $order->successfulPayment]) }}" wire:navigate variant="ghost">
                View receipt
            </flux:button>
        @endif
        <flux:button href="{{ route('tenant.subscription', $tenant) }}" wire:navigate>Back to subscription</flux:button>
    </flux:card>
</div>
