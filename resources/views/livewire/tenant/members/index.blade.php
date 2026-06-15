<?php

use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [TenantMembership::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'memberships' => $this->tenant->memberships()->with('user')->orderBy('role')->get(),
        ];
    }
}; ?>

<x-slot:heading>Members — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="overflow-hidden rounded-lg border border-zinc-800">
    <table class="min-w-full divide-y divide-zinc-800 text-sm">
        <thead class="bg-zinc-900 text-left text-zinc-400">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-800 bg-zinc-950 text-zinc-200">
            @foreach ($memberships as $membership)
                <tr>
                    <td class="px-4 py-3">{{ $membership->user->name }}</td>
                    <td class="px-4 py-3">{{ $membership->user->email }}</td>
                    <td class="px-4 py-3">{{ $membership->role->label() }}</td>
                    <td class="px-4 py-3">{{ $membership->status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
