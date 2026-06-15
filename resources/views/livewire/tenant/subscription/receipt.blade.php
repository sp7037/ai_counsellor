<?php

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public Payment $payment;

    public function mount(Tenant $tenant, Payment $payment): void
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->tenant_id === $tenant->id, 404);

        $this->tenant = $tenant;
        $this->payment = $payment->load(['paymentOrder.plan']);
    }
}; ?>

<x-slot:heading>Payment receipt</x-slot:heading>

<div class="mx-auto max-w-2xl">
    <flux:card class="grid gap-4 print:shadow-none">
        <div class="border-b border-zinc-800 pb-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Payment receipt</p>
            <flux:heading size="lg">{{ config('payments.platform_legal_name') }}</flux:heading>
            <flux:text class="text-zinc-400">This is a payment receipt, not a tax invoice.</flux:text>
        </div>

        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-zinc-500">Tenant</dt><dd>{{ $tenant->name }}</dd></div>
            <div><dt class="text-zinc-500">Plan</dt><dd>{{ $payment->paymentOrder->plan->name }}</dd></div>
            <div><dt class="text-zinc-500">Reference</dt><dd>{{ $payment->paymentOrder->internal_reference }}</dd></div>
            <div><dt class="text-zinc-500">Provider payment ID</dt><dd class="font-mono text-xs">{{ $payment->provider_payment_id }}</dd></div>
            <div><dt class="text-zinc-500">Date</dt><dd>{{ $payment->captured_at?->format('d M Y H:i') }}</dd></div>
            <div><dt class="text-zinc-500">Amount</dt><dd>{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</dd></div>
            <div><dt class="text-zinc-500">Status</dt><dd>{{ $payment->status->label() }}</dd></div>
        </dl>

        <div class="flex gap-2 print:hidden">
            <flux:button onclick="window.print()" size="sm">Print</flux:button>
            <flux:button href="{{ route('tenant.subscription', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
        </div>
    </flux:card>
</div>
