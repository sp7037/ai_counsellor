<?php

use App\Models\Payment;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'payments' => Payment::query()
                ->where('tenant_id', $this->tenant->id)
                ->with('paymentOrder.plan')
                ->latest()
                ->limit(50)
                ->get(),
        ];
    }
}; ?>

<x-slot:heading>{{ $tenant->name }} — payments</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<flux:card class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-zinc-500"><th class="pb-2">Plan</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr class="border-t border-zinc-800">
                    <td class="py-2">{{ $payment->paymentOrder->plan->name ?? '—' }}</td>
                    <td>{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</td>
                    <td>{{ $payment->status->label() }}</td>
                    <td>{{ $payment->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-4 text-zinc-500">No payments recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</flux:card>
