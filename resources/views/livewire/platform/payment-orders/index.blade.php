<?php

use App\Models\PaymentOrder;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.platform')] class extends Component {
    use WithPagination;

    public string $status = '';

    public function with(): array
    {
        $query = PaymentOrder::query()->with(['tenant', 'plan'])->latest();

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return [
            'orders' => $query->paginate(20),
        ];
    }
}; ?>

<x-slot:heading>Payment orders</x-slot:heading>

<div class="grid gap-4">
    <flux:select wire:model.live="status" class="max-w-xs">
        <option value="">All statuses</option>
        <option value="created">Awaiting payment</option>
        <option value="paid">Paid</option>
        <option value="failed">Failed</option>
        <option value="expired">Expired</option>
    </flux:select>

    <flux:card class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-zinc-500">
                    <th class="pb-2">Reference</th>
                    <th>Tenant</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr class="border-t border-zinc-800">
                        <td class="py-2 font-mono text-xs">{{ $order->internal_reference }}</td>
                        <td>{{ $order->tenant->name }}</td>
                        <td>{{ $order->plan->name }}</td>
                        <td>{{ $order->currency }} {{ number_format($order->amount_minor / 100, 2) }}</td>
                        <td><flux:badge size="sm">{{ $order->status->label() }}</flux:badge></td>
                        <td>{{ $order->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $orders->links() }}</div>
    </flux:card>
</div>
