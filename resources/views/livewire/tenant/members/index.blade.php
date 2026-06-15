<?php

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Tenancy\MembershipLifecycleService;
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
            'assignableRoles' => collect(TenantRole::cases())
                ->reject(fn (TenantRole $role) => $role === TenantRole::Owner)
                ->values(),
        ];
    }

    public function changeRole(int $membershipId, string $role, MembershipLifecycleService $service): void
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $this->tenant->id)
            ->findOrFail($membershipId);

        $service->changeRole($membership, TenantRole::from($role), Auth::user());
    }

    public function changeStatus(int $membershipId, string $status, MembershipLifecycleService $service): void
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $this->tenant->id)
            ->findOrFail($membershipId);

        $service->changeStatus($membership, MembershipStatus::from($status), Auth::user());
    }

    public function removeMember(int $membershipId, MembershipLifecycleService $service): void
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $this->tenant->id)
            ->findOrFail($membershipId);

        $service->removeMember($membership, Auth::user());
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
                <th class="px-4 py-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-800 bg-zinc-950 text-zinc-200">
            @foreach ($memberships as $membership)
                <tr wire:key="membership-{{ $membership->id }}">
                    <td class="px-4 py-3">{{ $membership->user->name }}</td>
                    <td class="px-4 py-3">{{ $membership->user->email }}</td>
                    <td class="px-4 py-3">{{ $membership->role->label() }}</td>
                    <td class="px-4 py-3">{{ $membership->status->label() }}</td>
                    <td class="px-4 py-3">
                        @can('updateRole', $membership)
                            <div class="flex flex-wrap gap-2">
                                @foreach ($assignableRoles as $roleOption)
                                    @if ($roleOption !== $membership->role)
                                        <flux:button
                                            wire:click="changeRole({{ $membership->id }}, '{{ $roleOption->value }}')"
                                            size="sm"
                                            variant="ghost"
                                        >
                                            Set {{ $roleOption->label() }}
                                        </flux:button>
                                    @endif
                                @endforeach
                            </div>
                        @endcan

                        @can('updateStatus', $membership)
                            @if ($membership->status === App\Enums\Tenancy\MembershipStatus::Active)
                                <flux:button
                                    wire:click="changeStatus({{ $membership->id }}, '{{ App\Enums\Tenancy\MembershipStatus::Inactive->value }}')"
                                    size="sm"
                                    variant="ghost"
                                >
                                    Deactivate
                                </flux:button>
                            @else
                                <flux:button
                                    wire:click="changeStatus({{ $membership->id }}, '{{ App\Enums\Tenancy\MembershipStatus::Active->value }}')"
                                    size="sm"
                                    variant="ghost"
                                >
                                    Activate
                                </flux:button>
                            @endif
                        @endcan

                        @can('delete', $membership)
                            <flux:button
                                wire:click="removeMember({{ $membership->id }})"
                                wire:confirm="Remove this member?"
                                size="sm"
                                variant="danger"
                            >
                                Remove
                            </flux:button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
