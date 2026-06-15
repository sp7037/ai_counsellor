<?php

use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Leads\LeadDirectoryService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('create', [\App\Models\Lead::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $directory): array
    {
        return [
            'counsellors' => TenantMembership::query()->with('user')->where('tenant_id', $this->tenant->id)->where('role', TenantRole::Staff->value)->get(),
            'workload' => $directory->counsellorWorkload($this->tenant),
        ];
    }
}; ?>

<x-slot:heading>Counsellors</x-slot:heading>
<x-slot:actions><flux:button href="{{ route('tenant.counsellors.create', $tenant) }}" wire:navigate variant="primary" size="sm">Add counsellor</flux:button></x-slot:actions>
<div class="overflow-x-auto rounded-lg border border-zinc-800">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-900 text-left text-zinc-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Open leads</th></tr></thead>
        <tbody class="divide-y divide-zinc-800 text-zinc-200">
            @foreach ($counsellors as $membership)
                @php $load = collect($workload)->firstWhere('user_id', $membership->user_id); @endphp
                <tr>
                    <td class="px-4 py-3">{{ $membership->user->name }}</td>
                    <td class="px-4 py-3">{{ $membership->user->email }}</td>
                    <td class="px-4 py-3">{{ $membership->status->label() }}</td>
                    <td class="px-4 py-3">{{ $load['open_leads'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
