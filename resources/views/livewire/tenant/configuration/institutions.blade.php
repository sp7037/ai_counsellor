<?php

use App\Enums\Configuration\CatalogueStatus;
use App\Models\Institution;
use App\Models\Tenant;
use App\Services\Configuration\TenantCatalogueService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $name = '';

    public string $description = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Institution::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return ['items' => Institution::query()->orderBy('sort_order')->orderBy('name')->get()];
    }

    public function create(TenantCatalogueService $service): void
    {
        $this->authorize('create', [Institution::class, $this->tenant]);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ]);
        $service->createInstitution($this->tenant, $validated, auth()->user());
        $this->reset('name', 'description', 'city', 'state', 'country');
    }

    public function toggleStatus(string $uuid, TenantCatalogueService $service): void
    {
        $item = Institution::query()->where('uuid', $uuid)->first() ?? abort(404);
        $this->authorize('update', $item);
        $status = $item->status === CatalogueStatus::Active ? CatalogueStatus::Inactive : CatalogueStatus::Active;
        $service->setInstitutionStatus($item, $status, auth()->user());
    }

    public function deleteItem(string $uuid, TenantCatalogueService $service): void
    {
        $item = Institution::query()->where('uuid', $uuid)->first() ?? abort(404);
        $this->authorize('delete', $item);
        $service->removeInstitution($item, auth()->user());
    }
}; ?>

<x-slot:heading>Institutions</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\Institution::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4 md:grid-cols-2">
            <flux:input wire:model="name" label="Institution name" required />
            <flux:input wire:model="city" label="City" />
            <flux:input wire:model="state" label="State" />
            <flux:input wire:model="country" label="Country" />
            <div class="md:col-span-2"><flux:textarea wire:model="description" label="Description" rows="2" /></div>
            <flux:button type="submit" variant="primary">Add institution</flux:button>
        </form>
    @endcan
    <div class="grid gap-3">
        @forelse ($items as $item)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $item->name }}</div>
                    <div class="text-zinc-500">{{ $item->status->label() }} @if($item->city) · {{ $item->city }} @endif</div>
                </div>
                @can('update', $item)
                    <div class="flex gap-2">
                        <flux:button wire:click="toggleStatus('{{ $item->uuid }}')" size="sm" variant="ghost">Toggle</flux:button>
                        <flux:button wire:click="deleteItem('{{ $item->uuid }}')" size="sm" variant="danger">Remove</flux:button>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-zinc-500">No institutions configured yet.</p>
        @endforelse
    </div>
</div>
