<?php

use App\Enums\Billing\PlanFeature;
use App\Enums\Leads\LeadStage;
use App\Enums\Leads\LeadTaskStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Billing\EntitlementResolver;
use App\Services\Leads\LeadDirectoryService;
use App\Services\Leads\LeadLifecycleService;
use App\Services\Leads\LeadTaskDirectoryService;
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

    #[Url]
    public string $assigned_to = '';

    #[Url]
    public string $task_filter = '';

    #[Url]
    public string $visibility = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Lead::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $directory, LeadTaskDirectoryService $tasks, EntitlementResolver $entitlements): array
    {
        $filters = array_filter([
            'search' => $this->search ?: null,
            'stage' => $this->stage ?: null,
            'assigned_to' => $this->assigned_to ?: null,
            'follow_up_due' => $this->task_filter === 'overdue' ? true : null,
            'deleted_only' => $this->visibility === 'deleted' ? true : null,
        ]);

        $leadPage = $directory->paginateForTenant($this->tenant, null, $filters);
        $leadIds = $leadPage->getCollection()->pluck('id');
        $latestTasks = LeadTask::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('lead_id', $leadIds)
            ->whereIn('status', [LeadTaskStatus::Pending->value, LeadTaskStatus::InProgress->value])
            ->orderBy('due_at')
            ->get()
            ->groupBy('lead_id')
            ->map(fn ($items) => $items->first());

        return [
            'allowed' => $entitlements->check($this->tenant, PlanFeature::LeadManagement)->isAllowed(),
            'leads' => $leadPage,
            'latestTasks' => $latestTasks,
            'metrics' => array_merge($directory->tenantMetrics($this->tenant), $tasks->tenantCounts($this->tenant)),
            'counsellors' => TenantMembership::query()->with('user')->where('tenant_id', $this->tenant->id)->where('role', TenantRole::Staff->value)->where('status', 'active')->get(),
        ];
    }

    public function restoreLead(string $leadUuid, LeadLifecycleService $lifecycle): void
    {
        $lead = Lead::onlyTrashed()->where('tenant_id', $this->tenant->id)->where('uuid', $leadUuid)->firstOrFail();
        $this->authorize('restore', $lead);
        $lifecycle->restore($lead, auth()->user());
    }
}; ?>

<x-slot:heading>Leads</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.leads.create', $tenant) }}" wire:navigate variant="primary" size="sm">Create lead</flux:button>
</x-slot:actions>

@if (! $allowed)
    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">Lead management is not enabled on your current plan.</div>
@else
<div class="grid gap-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['New leads' => $metrics['new_leads'], 'Unassigned' => $metrics['unassigned'], 'Tasks overdue' => $metrics['overdue'], 'Tasks today' => $metrics['today']] as $label => $value)
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
        <flux:select wire:model.live="assigned_to" class="sm:max-w-xs">
            <option value="">All counsellors</option>
            @foreach ($counsellors as $membership)
                <option value="{{ $membership->user_id }}">{{ $membership->user->name }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="task_filter" class="sm:max-w-xs">
            <option value="">All follow-ups</option>
            <option value="overdue">Overdue follow-ups</option>
        </flux:select>
        <flux:select wire:model.live="visibility" class="sm:max-w-xs">
            <option value="">Active leads</option>
            <option value="deleted">Deleted leads</option>
        </flux:select>
    </div>
    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-500"><tr>
                <th class="px-4 py-3">Reference</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Counsellor</th><th class="px-4 py-3">Latest task</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-zinc-800 text-zinc-200">
                @forelse ($leads as $lead)
                    @php $task = $latestTasks[$lead->id] ?? null; @endphp
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $lead->public_reference }}</td>
                        @php
                            $contactLabel = $lead->contactLabel();
                            $secondaryContact = $lead->mobile ?? $lead->email;
                        @endphp
                        <td class="px-4 py-3">{{ $contactLabel }}@if($secondaryContact && $contactLabel !== $secondaryContact)<br><span class="text-zinc-500">{{ $secondaryContact }}</span>@endif</td>
                        <td class="px-4 py-3">{{ $lead->stage->label() }}</td>
                        <td class="px-4 py-3">{{ $lead->assignee?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($task)
                                <span class="block">{{ $task->title }}</span>
                                <span class="text-xs text-zinc-500">{{ $task->displayStatus()->label() }} · {{ $task->due_at?->toDayDateTimeString() ?? 'No due date' }}</span>
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                @if ($visibility === 'deleted')
                                    <flux:button wire:click="restoreLead('{{ $lead->uuid }}')" size="sm" variant="primary">Restore</flux:button>
                                @else
                                    <flux:button href="{{ route('tenant.leads.show', [$tenant, $lead]) }}" wire:navigate size="sm" variant="ghost">View</flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">No leads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $leads->links() }}
</div>
@endif
