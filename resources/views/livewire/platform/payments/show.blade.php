<?php

use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Payment $payment;

    public function mount(Payment $payment): void
    {
        $this->payment = $payment->load(['tenant', 'paymentOrder.plan', 'events', 'paymentOrder.events']);
    }
}; ?>

<x-slot:heading>Payment detail</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.payments.index') }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    <flux:card class="grid gap-2 text-sm">
        <flux:heading size="md">Payment</flux:heading>
        <p>Tenant: {{ $payment->tenant->name }}</p>
        <p>Plan: {{ $payment->paymentOrder->plan->name ?? '—' }}</p>
        <p>Amount: {{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</p>
        <p>Status: {{ $payment->status->label() }}</p>
        <p>Provider payment ID: <span class="font-mono text-xs">{{ $payment->provider_payment_id }}</span></p>
        <p>Order reference: {{ $payment->paymentOrder->internal_reference }}</p>
        <p>Subscription activation: {{ $payment->paymentOrder->subscription_activation_completed_at ? 'Completed' : 'Pending' }}</p>
    </flux:card>

    <flux:card>
        <flux:heading size="md" class="mb-3">Event history</flux:heading>
        <ul class="grid gap-1 text-sm text-zinc-300">
            @foreach ($payment->paymentOrder->events as $event)
                <li>{{ $event->event_type->label() }} — {{ $event->created_at?->format('d M Y H:i') }}</li>
            @endforeach
        </ul>
    </flux:card>
</div>
