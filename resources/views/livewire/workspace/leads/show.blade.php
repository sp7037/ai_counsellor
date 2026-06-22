<?php

use App\Enums\Billing\PlanFeature;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Tenant;
use App\Services\Billing\EntitlementResolver;
use App\Services\Leads\LeadTaskService;
use App\Services\Leads\LeadWorkflowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    public Lead $lead;

    public string $note_body = '';

    public string $follow_up_at = '';

    public function mount(Tenant $tenant, Lead $lead): void
    {
        $this->authorize('view', $lead);
        $this->tenant = $tenant;
        $this->lead = $lead;
    }

    public function with(EntitlementResolver $entitlements): array
    {
        return [
            'allowed' => $entitlements->check($this->tenant, PlanFeature::CounsellorWorkspace)->isAllowed(),
            'activities' => $this->lead->activities()->with('actor')->latest('id')->limit(20)->get(),
            'tasks' => $this->lead->tasks()->whereIn('status', ['pending', 'in_progress'])->orderBy('due_at')->get(),
        ];
    }

    public function addNote(LeadWorkflowService $workflow): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $workflow->addNote($this->lead, Auth::user(), $this->note_body);
        $this->reset('note_body');
    }

    public function markContactAttempt(LeadWorkflowService $workflow): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $this->lead = $workflow->recordContactAttempt($this->lead, Auth::user());
    }

    public function startTask(int $taskId, LeadTaskService $tasks): void
    {
        $task = LeadTask::query()->findOrFail($taskId);
        $this->authorize('update', $task);
        $tasks->start($task, Auth::user());
    }

    public function completeTask(int $taskId, LeadTaskService $tasks): void
    {
        $task = LeadTask::query()->findOrFail($taskId);
        $this->authorize('update', $task);
        $tasks->complete($task, Auth::user());
        $this->lead->refresh();
    }

    public function scheduleFollowUp(LeadTaskService $tasks): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $this->validate(['follow_up_at' => ['required', 'date']]);
        $tasks->createForLead($this->lead, Auth::user(), [
            'title' => 'Follow-up with '.$this->lead->full_name,
            'due_at' => $this->follow_up_at,
            'assigned_to_user_id' => Auth::id(),
        ]);
        $this->reset('follow_up_at');
        $this->lead->refresh();
    }
}; ?>

<x-slot:heading>{{ $lead->public_reference }}</x-slot:heading>
<div class="grid gap-6">
    @if (! $allowed)
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">Counsellor workspace is not enabled on your current plan.</div>
    @endif
    <dl class="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm sm:grid-cols-2">
        <div><dt class="text-zinc-500">Name</dt><dd>{{ $lead->full_name }}</dd></div>
        <div><dt class="text-zinc-500">Stage</dt><dd>{{ $lead->stage->label() }}</dd></div>
        <div><dt class="text-zinc-500">Mobile</dt><dd>{{ $lead->mobile ?? '—' }}</dd></div>
        <div><dt class="text-zinc-500">Next follow-up</dt><dd>{{ $lead->next_follow_up_at?->toDayDateTimeString() ?? '—' }}</dd></div>
    </dl>
    @if ($tasks->isNotEmpty())
        <div class="rounded-lg border border-zinc-800 p-4">
            <h3 class="mb-3 text-sm font-semibold text-white">Open follow-up tasks</h3>
            <ul class="grid gap-2 text-sm">
                @foreach ($tasks as $task)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded border border-zinc-800 px-3 py-2">
                        <div>
                            <div class="font-medium">{{ $task->title }}</div>
                            <div class="text-xs text-zinc-500">{{ $task->displayStatus()->label() }} · {{ $task->due_at?->toDayDateTimeString() ?? 'No due date' }}</div>
                        </div>
                        <div class="flex gap-2">
                            @if ($allowed && $task->status->value === 'pending')
                                <flux:button wire:click="startTask({{ $task->id }})" size="sm" variant="ghost">Start</flux:button>
                            @endif
                            @if ($allowed && $task->status->isOpen())
                                <flux:button wire:click="completeTask({{ $task->id }})" size="sm" variant="primary">Complete</flux:button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="flex flex-wrap gap-2">
        <flux:button wire:click="markContactAttempt" variant="primary">Record contact attempt</flux:button>
    </div>
    @if ($allowed)
        <form wire:submit="scheduleFollowUp" class="grid max-w-md gap-3 rounded-lg border border-zinc-800 p-4">
            <flux:input wire:model="follow_up_at" type="datetime-local" label="Schedule follow-up task" required />
            <flux:button type="submit" variant="primary">Save follow-up</flux:button>
        </form>
    @endif
    <form wire:submit="addNote" class="grid gap-3"><flux:textarea wire:model="note_body" label="Internal note" rows="3" required /><flux:button type="submit">Add note</flux:button></form>
    <div>
        <h3 class="mb-2 text-sm font-semibold text-white">Recent timeline</h3>
        <ul class="grid gap-2 text-sm">
            @foreach ($activities as $activity)
                <li class="rounded border border-zinc-800 px-3 py-2">
                    <div class="font-medium">{{ $activity->title ?? $activity->action_type->label() }}</div>
                    <div class="text-xs text-zinc-500">{{ $activity->created_at?->diffForHumans() }}</div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
