<?php

use App\Models\PaymentOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public PaymentOrder $order;

    public string $reason = 'unknown';

    public function mount(Tenant $tenant, PaymentOrder $order): void
    {
        Gate::authorize('view', $order);
        abort_unless($order->tenant_id === $tenant->id, 404);

        $this->tenant = $tenant;
        $this->order = $order;
        $this->reason = (string) request()->query('reason', 'unknown');
    }

    public function with(): array
    {
        $message = match ($this->reason) {
            'cancelled' => 'Payment was cancelled. You can try again when ready.',
            'verification' => 'Payment could not be verified. If money was deducted, contact support with your reference.',
            default => 'Payment was not completed.',
        };

        return compact('message');
    }
}; ?>

<x-slot:heading>Payment not completed</x-slot:heading>

<div class="mx-auto max-w-lg grid gap-4">
    <flux:callout variant="warning">{{ $message }}</flux:callout>
    <flux:card class="grid gap-3">
        <flux:text>Reference: {{ $order->internal_reference }}</flux:text>
        <flux:text class="text-zinc-400">Status: {{ $order->status->label() }}</flux:text>
        <div class="flex gap-2">
            <flux:button href="{{ route('tenant.subscription.checkout', [$tenant, $order->plan]) }}" wire:navigate>Try again</flux:button>
            <flux:button href="{{ route('tenant.subscription', $tenant) }}" wire:navigate variant="ghost">Subscription</flux:button>
        </div>
    </flux:card>
</div>
