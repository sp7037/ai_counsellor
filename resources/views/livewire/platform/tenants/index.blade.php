<?php

use App\Models\Tenant;
use App\Services\Platform\PlatformTenantDirectoryService;
use App\Services\Platform\TenantAiStatusPresenter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.platform')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sort = 'created_at';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function with(PlatformTenantDirectoryService $directory, TenantAiStatusPresenter $aiStatus): array
    {
        $tenants = $directory->paginate(
            search: $this->search !== '' ? $this->search : null,
            status: $this->status !== '' ? $this->status : null,
            sort: $this->sort,
        );

        return compact('tenants', 'aiStatus');
    }
}; ?>

<x-slot:heading>Tenants</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.create') }}" wire:navigate variant="primary" size="sm">Create tenant</flux:button>
</x-slot:actions>

<div class="grid gap-4">
    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search name, slug, or UUID" class="sm:max-w-xs" />
        <flux:select wire:model.live="status" class="sm:max-w-xs">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="pending">Pending</option>
        </flux:select>
        <flux:select wire:model.live="sort" class="sm:max-w-xs">
            <option value="created_at">Sort: created</option>
            <option value="name">Sort: name</option>
            <option value="last_activity_at">Sort: activity</option>
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="hidden lg:table-cell px-4 py-3">Credential mode</th>
                    <th class="hidden md:table-cell px-4 py-3">AI status</th>
                    <th class="hidden md:table-cell px-4 py-3">Conversations</th>
                    <th class="hidden xl:table-cell px-4 py-3">Activity</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 text-zinc-200">
                @forelse ($tenants as $tenant)
                    @php $ai = $aiStatus->summarize($tenant->aiConfig); @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $tenant->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $tenant->slug }}</td>
                        <td class="px-4 py-3">{{ $tenant->status->label() }}</td>
                        <td class="hidden lg:table-cell px-4 py-3">{{ $aiStatus->credentialModeLabel($tenant->aiConfig) }}</td>
                        <td class="hidden md:table-cell px-4 py-3">{{ $ai['label'] }}</td>
                        <td class="hidden md:table-cell px-4 py-3">{{ $tenant->conversations_count }}</td>
                        <td class="hidden xl:table-cell px-4 py-3">{{ $tenant->last_activity_at ? \Illuminate\Support\Carbon::parse($tenant->last_activity_at)->diffForHumans() : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <flux:button href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate size="sm" variant="ghost">View</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-zinc-500">No tenants match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tenants->links() }}
</div>
