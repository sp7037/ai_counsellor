<?php

use App\Models\Tenant;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        $user = Auth::user();

        $tenants = $user->activeMemberships()
            ->with('tenant')
            ->get()
            ->map(fn ($membership) => $membership->tenant)
            ->filter(fn (Tenant $tenant) => $tenant->allowsTenantAccess())
            ->values();

        return [
            'tenants' => $tenants,
        ];
    }

    public function select(string $tenantUuid, PostLoginRedirect $redirects): void
    {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();

        abort_unless(Auth::user()->hasActiveMembership($tenant), 403);
        abort_unless($tenant->allowsTenantAccess(), 403);

        $this->redirect(route('tenant.dashboard', $tenant), navigate: true);
    }
}; ?>

<x-slot:heading>Select organisation</x-slot:heading>

<div class="grid gap-4">
    @forelse ($tenants as $tenant)
        <button
            wire:click="select('{{ $tenant->uuid }}')"
            type="button"
            class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 text-left hover:border-zinc-500"
        >
            <div class="font-medium text-white">{{ $tenant->name }}</div>
            <div class="text-sm text-zinc-400">{{ $tenant->status->label() }}</div>
        </button>
    @empty
        <p class="text-zinc-400">No active organisation memberships are available.</p>
    @endforelse
</div>
