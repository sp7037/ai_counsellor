<?php

use App\Enums\Knowledge\KnowledgeFeeType;
use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\KnowledgeFee;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeFeeService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $label = '';

    public string $feeType = 'exact';

    public int $amountMinor = 0;

    public ?int $amountMaxMinor = null;

    public string $currency = 'INR';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [KnowledgeFee::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'fees' => KnowledgeFee::query()->orderByDesc('updated_at')->get(),
            'feeTypes' => KnowledgeFeeType::cases(),
        ];
    }

    public function create(KnowledgeFeeService $service): void
    {
        $this->authorize('create', [KnowledgeFee::class, $this->tenant]);
        $validated = $this->validate([
            'label' => ['required', 'string', 'max:160'],
            'feeType' => ['required', 'string'],
            'amountMinor' => ['required', 'integer', 'min:0'],
            'amountMaxMinor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $service->create($this->tenant, [
            'label' => $validated['label'],
            'fee_type' => $validated['feeType'],
            'amount_minor' => $validated['amountMinor'],
            'amount_max_minor' => $validated['amountMaxMinor'],
            'currency' => $validated['currency'],
        ], auth()->user());

        $this->reset('label', 'amountMinor', 'amountMaxMinor');
    }

    public function publish(string $uuid, KnowledgeFeeService $service): void
    {
        $fee = KnowledgeFee::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('publish', $fee);
        $service->publish($fee, auth()->user());
    }

    public function archive(string $uuid, KnowledgeFeeService $service): void
    {
        $fee = KnowledgeFee::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('archive', $fee);
        $service->archive($fee, auth()->user());
    }
}; ?>

<x-slot:heading>Fees</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\KnowledgeFee::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4 md:grid-cols-2">
            <flux:input wire:model="label" label="Label" required class="md:col-span-2" />
            <flux:select wire:model="feeType" label="Fee type">
                @foreach ($feeTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->value }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="currency" label="Currency (ISO)" maxlength="3" />
            <flux:input wire:model="amountMinor" type="number" label="Amount (minor units)" min="0" />
            @if ($feeType === 'range')
                <flux:input wire:model="amountMaxMinor" type="number" label="Maximum (minor units)" min="0" />
            @endif
            <flux:button type="submit" variant="primary" class="md:col-span-2">Add fee</flux:button>
        </form>
    @endcan

    <div class="grid gap-3">
        @forelse ($fees as $fee)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $fee->label }}</div>
                    <div class="text-zinc-500">{{ $fee->fee_type->value }} · {{ $fee->currency }} {{ number_format($fee->amount_minor / 100, 2) }} · {{ $fee->status->value }}</div>
                </div>
                @can('update', $fee)
                    <div class="flex gap-2">
                        @if ($fee->status !== KnowledgePublishableStatus::Published)
                            <flux:button wire:click="publish('{{ $fee->uuid }}')" size="sm">Publish</flux:button>
                        @endif
                        @if ($fee->status !== KnowledgePublishableStatus::Archived)
                            <flux:button wire:click="archive('{{ $fee->uuid }}')" size="sm" variant="ghost">Archive</flux:button>
                        @endif
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-zinc-500">No fees configured yet.</p>
        @endforelse
    </div>
</div>
