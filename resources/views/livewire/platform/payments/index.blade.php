<?php

use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.platform')] class extends Component {
    use WithPagination;

    public string $status = '';

    public function with(): array
    {
        $query = Payment::query()->with(['tenant', 'paymentOrder.plan'])->latest();

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return [
            'payments' => $query->paginate(20),
        ];
    }
}; ?>

<x-slot:heading>Payments</x-slot:heading>

<div class="grid gap-4">
    <flux:select wire:model.live="status" class="max-w-xs">
        <option value="">All statuses</option>
        <option value="captured">Captured</option>
        <option value="failed">Failed</option>
    </flux:select>

    <flux:card class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-zinc-500">
                    <th class="pb-2">Tenant</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr class="border-t border-zinc-800">
                        <td class="py-2">{{ $payment->tenant->name }}</td>
                        <td>{{ $payment->paymentOrder->plan->name ?? '—' }}</td>
                        <td>{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</td>
                        <td><flux:badge size="sm">{{ $payment->status->label() }}</flux:badge></td>
                        <td>{{ $payment->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('platform.payments.show', $payment) }}" wire:navigate class="text-sky-400">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $payments->links() }}</div>
    </flux:card>
</div>
