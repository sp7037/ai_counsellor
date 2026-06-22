<?php

use App\Enums\Billing\PlanFeature;
use App\Enums\Leads\LeadTaskPriority;
use App\Enums\Leads\LeadTaskStatus;
use App\Enums\Leads\LeadTaskType;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Tenant;
use App\Services\Billing\EntitlementResolver;
use App\Services\Leads\LeadTaskDirectoryService;
use App\Services\Leads\LeadTaskService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    #[Url]
    public string $filter = 'today';

    public string $next_title = '';

    public string $next_due_at = '';

    public ?int $completing_task_id = null;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(LeadTaskDirectoryService $directory, EntitlementResolver $entitlements): array
    {
        $user = Auth::user();
        $allowed = $entitlements->check($this->tenant, PlanFeature::CounsellorWorkspace)->isAllowed();
        $filters = match ($this->filter) {
            'overdue' => ['due_overdue' => true],
            'upcoming' => ['upcoming' => true],
            'pending' => ['status' => LeadTaskStatus::Pending->value],
            'completed' => ['status' => LeadTaskStatus::Completed->value],
            default => ['due_today' => true],
        };

        return [
            'allowed' => $allowed,
            'counts' => $allowed ? $directory->counsellorCounts($this->tenant, $user) : [],
            'tasks' => $allowed ? $directory->listForCounsellor($this->tenant, $user, $filters) : collect(),
        ];
    }

    public function startTask(int $taskId, LeadTaskService $tasks): void
    {
        $task = LeadTask::query()->with('lead')->findOrFail($taskId);
        $this->authorize('update', $task);
        $tasks->start($task, Auth::user());
    }

    public function completeTask(int $taskId, LeadTaskService $tasks): void
    {
        $task = LeadTask::query()->with('lead')->findOrFail($taskId);
        $this->authorize('update', $task);
        $tasks->complete($task, Auth::user());
        $this->completing_task_id = null;
    }

    public function scheduleNext(int $taskId, LeadTaskService $tasks): void
    {
        $task = LeadTask::query()->with('lead')->findOrFail($taskId);
        $this->authorize('update', $task);
        $this->validate([
            'next_title' => ['required', 'string', 'max:255'],
            'next_due_at' => ['required', 'date'],
        ]);

        $tasks->complete($task, Auth::user(), 'Completed with next follow-up scheduled');
        $tasks->createForLead($task->lead, Auth::user(), [
            'title' => $this->next_title,
            'due_at' => $this->next_due_at,
            'assigned_to_user_id' => Auth::id(),
        ]);

        $this->reset('next_title', 'next_due_at', 'completing_task_id');
    }
}; ?>

<x-slot:heading>My Follow-ups</x-slot:heading>

@if (! $allowed)
    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
        Counsellor workspace follow-ups are not enabled on your current plan.
    </div>
@else
    <div class="grid gap-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                'today' => ['Today', $counts['today'] ?? 0],
                'overdue' => ['Overdue', $counts['overdue'] ?? 0],
                'pending' => ['Pending', $counts['pending'] ?? 0],
                'completed' => ['Completed today', $counts['completed'] ?? 0],
            ] as $key => [$label, $value])
                <button type="button" wire:click="$set('filter', '{{ $key }}')" @class([
                    'rounded-lg border p-4 text-left transition',
                    'border-sky-500/50 bg-sky-500/10' => $filter === $key,
                    'border-zinc-800 bg-zinc-900 hover:border-zinc-700' => $filter !== $key,
                ])>
                    <p class="text-xs text-zinc-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $value }}</p>
                </button>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-900 text-left text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Lead</th>
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800 text-zinc-200">
                    @forelse ($tasks as $task)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('workspace.leads.show', [$tenant, $task->lead]) }}" class="font-mono text-xs underline" wire:navigate>{{ $task->lead->public_reference }}</a>
                                <div class="text-zinc-500">{{ $task->lead->full_name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $task->title }}</div>
                                <div class="text-xs text-zinc-500">{{ $task->task_type->label() }} · {{ $task->priority->label() }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $task->due_at?->toDayDateTimeString() ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $task->displayStatus()->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($task->status === LeadTaskStatus::Pending || $task->displayStatus() === LeadTaskStatus::Overdue)
                                        <flux:button wire:click="startTask({{ $task->id }})" size="sm" variant="ghost">Start</flux:button>
                                    @endif
                                    @if ($task->status->isOpen())
                                        <flux:button wire:click="completeTask({{ $task->id }})" size="sm" variant="primary">Complete</flux:button>
                                        <flux:button wire:click="$set('completing_task_id', {{ $task->id }})" size="sm" variant="ghost">Complete & schedule next</flux:button>
                                    @endif
                                </div>
                                @if ($completing_task_id === $task->id)
                                    <form wire:submit="scheduleNext({{ $task->id }})" class="mt-3 grid gap-2 rounded border border-zinc-700 p-3 text-left">
                                        <flux:input wire:model="next_title" label="Next follow-up title" required />
                                        <flux:input wire:model="next_due_at" type="datetime-local" label="Due at" required />
                                        <flux:button type="submit" size="sm" variant="primary">Save next follow-up</flux:button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">No follow-up tasks in this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
