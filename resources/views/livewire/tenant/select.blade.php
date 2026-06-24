<?php

use App\Models\Tenant;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(PostLoginRedirect $redirects): array
    {
        $user = Auth::user();

        $tenants = $redirects->accessibleMemberships($user)
            ->map(fn ($membership) => $membership->tenant)
            ->values();

        return [
            'tenants' => $tenants,
        ];
    }

    public function select(string $tenantUuid, PostLoginRedirect $redirects): void
    {
        $tenant = Tenant::query()->where('uuid', $tenantUuid)->firstOrFail();

        abort_unless(Auth::user()->hasActiveMembership($tenant), 403);
        abort_unless($tenant->allowsWorkspaceEntry(), 403);

        $role = Auth::user()->tenantRoleFor($tenant);

        if ($role?->usesCounsellorWorkspace()) {
            $this->redirect(route('workspace.dashboard', $tenant), navigate: true);

            return;
        }

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
            <div class="font-medium text-white">{{ $tenant->displayName() }}</div>
            <div class="text-sm text-zinc-400">{{ $tenant->status->label() }}</div>
        </button>
    @empty
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-6 text-sm text-amber-100">
            <p class="font-medium text-white">No organisation workspace is available for your account.</p>
            <p class="mt-2 text-amber-100/90">
                This can happen if your organisation is archived or deleted, or if your membership is inactive.
                Contact your platform administrator if you believe this is a mistake.
            </p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="text-sm font-medium text-white underline">Log out</button>
            </form>
        </div>
    @endforelse
</div>
