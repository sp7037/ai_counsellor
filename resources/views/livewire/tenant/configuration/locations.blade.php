<?php

use App\Enums\Configuration\CatalogueStatus;
use App\Models\Location;
use App\Models\Tenant;
use App\Services\Configuration\TenantCatalogueService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public string $name = '';

    public string $addressLine = '';

    public string $city = '';

    public string $state = '';

    public string $pinCode = '';

    public string $phone = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Location::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return ['items' => Location::query()->orderBy('sort_order')->orderBy('name')->get()];
    }

    public function create(TenantCatalogueService $service): void
    {
        $this->authorize('create', [Location::class, $this->tenant]);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'addressLine' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'pinCode' => ['nullable', 'string', 'max:16'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
        $service->createLocation($this->tenant, [
            'name' => $validated['name'],
            'address_line' => $validated['addressLine'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'pin_code' => $validated['pinCode'],
            'phone' => $validated['phone'],
        ], auth()->user());
        $this->reset('name', 'addressLine', 'city', 'state', 'pinCode', 'phone');
    }

    public function toggleStatus(string $uuid, TenantCatalogueService $service): void
    {
        $item = Location::query()->where('uuid', $uuid)->first() ?? abort(404);
        $this->authorize('update', $item);
        $status = $item->status === CatalogueStatus::Active ? CatalogueStatus::Inactive : CatalogueStatus::Active;
        $service->setLocationStatus($item, $status, auth()->user());
    }

    public function deleteItem(string $uuid, TenantCatalogueService $service): void
    {
        $item = Location::query()->where('uuid', $uuid)->first() ?? abort(404);
        $this->authorize('delete', $item);
        $service->removeLocation($item, auth()->user());
    }
}; ?>

<x-slot:heading>Locations</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\Location::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4 md:grid-cols-2">
            <flux:input wire:model="name" label="Location name" required />
            <flux:input wire:model="phone" label="Phone" />
            <flux:input wire:model="addressLine" label="Address" class="md:col-span-2" />
            <flux:input wire:model="city" label="City" />
            <flux:input wire:model="state" label="State" />
            <flux:input wire:model="pinCode" label="PIN code" />
            <flux:button type="submit" variant="primary">Add location</flux:button>
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
            <p class="text-zinc-500">No locations configured yet.</p>
        @endforelse
    </div>
</div>
