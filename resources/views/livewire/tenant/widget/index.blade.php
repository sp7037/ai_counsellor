<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\WidgetKey;
use App\Services\Widget\TenantDomainService;
use App\Services\Widget\WidgetKeyService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public string $keyName = 'Default widget key';

    public string $domain = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [WidgetKey::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'keys' => WidgetKey::query()->latest()->get(),
            'domains' => TenantDomain::query()->latest()->get(),
            'embedBase' => url('/build'),
            'gatewayBase' => url('/widget/v1'),
        ];
    }

    public function createKey(WidgetKeyService $service): void
    {
        $this->authorize('create', [WidgetKey::class, $this->tenant]);

        $validated = $this->validate([
            'keyName' => ['required', 'string', 'max:120'],
        ]);

        $service->create($this->tenant, $validated['keyName'], auth()->user());
        $this->reset('keyName');
    }

    public function rotateKey(string $keyUuid, WidgetKeyService $service): void
    {
        $key = WidgetKey::query()->where('uuid', $keyUuid)->first();

        if ($key === null) {
            abort(404);
        }

        $this->authorize('rotate', $key);
        $service->rotate($key, auth()->user());
    }

    public function revokeKey(string $keyUuid, WidgetKeyService $service): void
    {
        $key = WidgetKey::query()->where('uuid', $keyUuid)->first();

        if ($key === null) {
            abort(404);
        }

        $this->authorize('revoke', $key);
        $service->revoke($key, auth()->user());
    }

    public function addDomain(TenantDomainService $service): void
    {
        $this->authorize('create', [TenantDomain::class, $this->tenant]);

        $validated = $this->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $service->add($this->tenant, $validated['domain'], auth()->user());
        $this->reset('domain');
    }

    public function verifyDomain(int $domainId, TenantDomainService $service): void
    {
        $record = TenantDomain::query()->find($domainId);

        if ($record === null) {
            abort(404);
        }

        $this->authorize('verify', $record);
        $service->verify($record, auth()->user());
    }

    public function removeDomain(int $domainId, TenantDomainService $service): void
    {
        $record = TenantDomain::query()->find($domainId);

        if ($record === null) {
            abort(404);
        }

        $this->authorize('delete', $record);
        $service->remove($record, auth()->user());
    }
}; ?>

<x-slot:heading>Chat widget — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
    <flux:button href="{{ route('tenant.widget.conversations', $tenant) }}" wire:navigate variant="ghost" size="sm">Conversations</flux:button>
</x-slot:actions>

<div class="grid gap-8">
    <section class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <flux:heading size="md">Widget keys</flux:heading>
        @can('create', [App\Models\WidgetKey::class, $tenant])
            <form wire:submit="createKey" class="flex flex-wrap items-end gap-3">
                <flux:input wire:model="keyName" label="Key name" class="min-w-64" />
                <flux:button type="submit" variant="primary">Create key</flux:button>
            </form>
        @endcan
        <div class="grid gap-3">
            @forelse ($keys as $key)
                <div class="rounded border border-zinc-800 p-3 text-sm">
                    <div class="font-medium text-white">{{ $key->name }}</div>
                    <div class="mt-1 font-mono text-xs text-zinc-400">{{ $key->public_key }}</div>
                    <div class="mt-1 text-zinc-500">Status: {{ $key->status->label() }}</div>
                    @if ($key->isActive())
                        <div class="mt-2 flex gap-2">
                            @can('rotate', $key)
                                <flux:button wire:click="rotateKey('{{ $key->uuid }}')" size="sm" variant="ghost">Rotate</flux:button>
                            @endcan
                            @can('revoke', $key)
                                <flux:button wire:click="revokeKey('{{ $key->uuid }}')" size="sm" variant="danger">Revoke</flux:button>
                            @endcan
                        </div>
                        <pre class="mt-3 overflow-x-auto rounded bg-zinc-950 p-2 text-xs text-zinc-300">&lt;script async src="{{ asset('build/widget.js') }}" data-widget-key="{{ $key->public_key }}" data-gateway="{{ $gatewayBase }}"&gt;&lt;/script&gt;</pre>
                    @endif
                </div>
            @empty
                <p class="text-zinc-500">No widget keys yet.</p>
            @endforelse
        </div>
    </section>

    <section class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <flux:heading size="md">Allowed domains</flux:heading>
        @can('create', [App\Models\TenantDomain::class, $tenant])
            <form wire:submit="addDomain" class="flex flex-wrap items-end gap-3">
                <flux:input wire:model="domain" label="Domain (example.com)" class="min-w-64" />
                <flux:button type="submit" variant="primary">Add domain</flux:button>
            </form>
        @endcan
        <div class="grid gap-3">
            @forelse ($domains as $record)
                <div class="flex items-center justify-between rounded border border-zinc-800 p-3 text-sm">
                    <div>
                        <div class="font-medium text-white">{{ $record->domain }}</div>
                        <div class="text-zinc-500">Status: {{ $record->status->label() }}</div>
                    </div>
                    <div class="flex gap-2">
                        @can('verify', $record)
                            @if ($record->status->value === 'pending')
                                <flux:button wire:click="verifyDomain({{ $record->id }})" size="sm">Verify</flux:button>
                            @endif
                        @endcan
                        @can('delete', $record)
                            <flux:button wire:click="removeDomain({{ $record->id }})" size="sm" variant="danger">Remove</flux:button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-zinc-500">No domains configured. Widget requests will be rejected until a verified domain exists (localhost allowed in local dev).</p>
            @endforelse
        </div>
    </section>

    <p class="text-sm text-zinc-500">
        Welcome messages, assistant identity and branding are managed under
        <a href="{{ route('tenant.configuration.index', $tenant) }}" class="text-blue-400 underline" wire:navigate>Configuration</a>.
    </p>
</div>
