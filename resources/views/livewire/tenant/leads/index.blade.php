<?php

use App\Enums\Leads\LeadSource;
use App\Enums\Leads\LeadStage;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\Leads\LeadDirectoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.tenant')] class extends Component {
    use WithPagination;

    public Tenant $tenant;

    #[Url]
    public string $search = '';

    #[Url]
    public string $stage = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Lead::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $directory): array
    {
        return [
            'leads' => $directory->paginateForTenant($this->tenant, null, array_filter([
                'search' => $this->search ?: null,
                'stage' => $this->stage ?: null,
            ])),
            'metrics' => $directory->tenantMetrics($this->tenant),
        ];
    }
}; ?>

<x-slot:heading>Leads</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.leads.create', $tenant) }}" wire:navigate variant="primary" size="sm">Create lead</flux:button>
</x-slot:actions>

<div class="grid gap-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['New leads' => $metrics['new_leads'], 'Unassigned' => $metrics['unassigned'], 'Follow-ups due' => $metrics['follow_ups_due'], 'Converted' => $metrics['converted']] as $label => $value)
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs text-zinc-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search leads" class="sm:max-w-xs" />
        <flux:select wire:model.live="stage" class="sm:max-w-xs">
            <option value="">All stages</option>
            @foreach (LeadStage::cases() as $leadStage)
                <option value="{{ $leadStage->value }}">{{ $leadStage->label() }}</option>
            @endforeach
        </flux:select>
    </div>
    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-500"><tr>
                <th class="px-4 py-3">Reference</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Counsellor</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-zinc-800 text-zinc-200">
                @forelse ($leads as $lead)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $lead->public_reference }}</td>
                        <td class="px-4 py-3">{{ $lead->full_name }}<br><span class="text-zinc-500">{{ $lead->mobile ?? $lead->email }}</span></td>
                        <td class="px-4 py-3">{{ $lead->stage->label() }}</td>
                        <td class="px-4 py-3">{{ $lead->priority->label() }}</td>
                        <td class="px-4 py-3">{{ $lead->assignee?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><flux:button href="{{ route('tenant.leads.show', [$tenant, $lead]) }}" wire:navigate size="sm" variant="ghost">View</flux:button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">No leads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $leads->links() }}
</div>
