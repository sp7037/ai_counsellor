<?php

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        $context = app(TenantContext::class);

        return [
            'membership' => $context->membership(),
            'user' => Auth::user(),
        ];
    }
}; ?>

<x-slot:heading>Tenant dashboard</x-slot:heading>

<div class="grid gap-6 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
    <div>
        <flux:heading size="lg">{{ $tenant->name }}</flux:heading>
        <flux:subheading>Status: {{ $tenant->status->label() }}</flux:subheading>
    </div>

    <dl class="grid gap-3 text-sm text-zinc-300">
        <div><dt class="text-zinc-500">Authenticated user</dt><dd>{{ $user->name }} ({{ $user->email }})</dd></div>
        <div><dt class="text-zinc-500">Membership role</dt><dd>{{ $membership?->role?->label() ?? 'Platform access' }}</dd></div>
        <div><dt class="text-zinc-500">Public tenant identifier</dt><dd class="font-mono text-xs">{{ $tenant->uuid }}</dd></div>
    </dl>

    <div class="flex gap-3">
        @can('viewAny', [App\Models\TenantMembership::class, $tenant])
            <flux:button href="{{ route('tenant.members.index', $tenant) }}" wire:navigate>View members</flux:button>
        @endcan
        <flux:button href="{{ route('tenant.widget.index', $tenant) }}" wire:navigate variant="ghost">Chat widget</flux:button>
        <flux:button href="{{ route('tenant.notes.index', $tenant) }}" wire:navigate variant="ghost">Tenant notes</flux:button>
    </div>
</div>
