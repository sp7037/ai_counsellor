<?php

use App\Models\Tenant;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public string $suspension_reason = '';

    public function mount(Tenant $tenant): void
    {
        Gate::authorize('view', $tenant);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'memberships' => $this->tenant->memberships()->with('user')->get(),
        ];
    }

    public function activate(TenantLifecycleService $service): void
    {
        Gate::authorize('activate', $this->tenant);
        $this->tenant = $service->activate($this->tenant, auth()->user());
    }

    public function suspend(TenantLifecycleService $service): void
    {
        Gate::authorize('suspend', $this->tenant);

        $this->validate([
            'suspension_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->tenant = $service->suspend($this->tenant, $this->suspension_reason, auth()->user());
        $this->reset('suspension_reason');
    }

    public function reactivate(TenantLifecycleService $service): void
    {
        Gate::authorize('reactivate', $this->tenant);
        $this->tenant = $service->reactivate($this->tenant, auth()->user());
    }
}; ?>

<x-slot:heading>{{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.index') }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm text-zinc-300">
        <dl class="grid gap-2">
            <div><dt class="text-zinc-500">Status</dt><dd>{{ $tenant->status->label() }}</dd></div>
            <div><dt class="text-zinc-500">Slug</dt><dd class="font-mono text-xs">{{ $tenant->slug }}</dd></div>
            <div><dt class="text-zinc-500">UUID</dt><dd class="font-mono text-xs">{{ $tenant->uuid }}</dd></div>
            @if ($tenant->suspension_reason)
                <div><dt class="text-zinc-500">Suspension reason</dt><dd>{{ $tenant->suspension_reason }}</dd></div>
            @endif
        </dl>

        <div class="mt-4 flex flex-wrap gap-3">
            @can('activate', $tenant)
                <flux:button wire:click="activate" variant="primary">Activate</flux:button>
            @endcan
            @can('reactivate', $tenant)
                <flux:button wire:click="reactivate" variant="primary">Reactivate</flux:button>
            @endcan
        </div>

        @can('suspend', $tenant)
            <form wire:submit="suspend" class="mt-4 grid gap-3">
                <flux:textarea wire:model="suspension_reason" label="Suspension reason" rows="3" required />
                <flux:button type="submit" variant="danger">Suspend tenant</flux:button>
            </form>
        @endcan
    </div>

    <div>
        <flux:heading size="md">Memberships</flux:heading>
        <div class="mt-3 overflow-hidden rounded-lg border border-zinc-800">
            <table class="min-w-full divide-y divide-zinc-800 text-sm">
                <thead class="bg-zinc-900 text-left text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800 bg-zinc-950 text-zinc-200">
                    @foreach ($memberships as $membership)
                        <tr>
                            <td class="px-4 py-3">{{ $membership->user->name }} ({{ $membership->user->email }})</td>
                            <td class="px-4 py-3">{{ $membership->role->label() }}</td>
                            <td class="px-4 py-3">{{ $membership->status->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
