<?php

use App\Enums\Configuration\CatalogueStatus;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\Configuration\TenantCatalogueService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $name = '';

    public string $description = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Service::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return ['items' => Service::query()->orderBy('sort_order')->orderBy('name')->get()];
    }

    public function create(TenantCatalogueService $service): void
    {
        $this->authorize('create', [Service::class, $this->tenant]);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->createService($this->tenant, $validated, auth()->user());
        $this->reset('name', 'description');
    }

    public function toggleStatus(string $uuid, TenantCatalogueService $service): void
    {
        $item = Service::query()->where('uuid', $uuid)->first();
        if ($item === null) {
            abort(404);
        }
        $this->authorize('update', $item);
        $status = $item->status === CatalogueStatus::Active ? CatalogueStatus::Inactive : CatalogueStatus::Active;
        $service->setServiceStatus($item, $status, auth()->user());
    }

    public function deleteItem(string $uuid, TenantCatalogueService $service): void
    {
        $item = Service::query()->where('uuid', $uuid)->first();
        if ($item === null) {
            abort(404);
        }
        $this->authorize('delete', $item);
        $service->removeService($item, auth()->user());
    }
}; ?>

<x-slot:heading>Services</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\Service::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:input wire:model="name" label="Service name" required />
            <flux:textarea wire:model="description" label="Description" rows="2" />
            <flux:button type="submit" variant="primary">Add service</flux:button>
        </form>
    @endcan
    <div class="grid gap-3">
        @forelse ($items as $item)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $item->name }}</div>
                    <div class="text-zinc-500">{{ $item->status->label() }}</div>
                    @if ($item->description)<p class="mt-1 text-zinc-400">{{ $item->description }}</p>@endif
                </div>
                @can('update', $item)
                    <div class="flex gap-2">
                        <flux:button wire:click="toggleStatus('{{ $item->uuid }}')" size="sm" variant="ghost">{{ $item->status === App\Enums\Configuration\CatalogueStatus::Active ? 'Deactivate' : 'Activate' }}</flux:button>
                        <flux:button wire:click="deleteItem('{{ $item->uuid }}')" size="sm" variant="danger">Remove</flux:button>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-zinc-500">No services configured yet.</p>
        @endforelse
    </div>
</div>
