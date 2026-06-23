<?php

use App\Enums\Billing\PlanFeature;
use App\Enums\Leads\LeadStage;
use App\Enums\Leads\LeadTaskPriority;
use App\Enums\Leads\LeadTaskType;
use App\Enums\Tenancy\TenantRole;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Leads\LeadAssignmentService;
use App\Services\Leads\LeadLifecycleService;
use App\Services\Leads\LeadTaskService;
use App\Services\Leads\LeadWorkflowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public Lead $lead;

    public string $tab = 'overview';

    public ?int $assign_to = null;

    public string $assignment_note = '';

    public string $note_body = '';

    public string $lost_reason = '';

    public string $task_title = '';

    public string $task_description = '';

    public string $task_due_at = '';

    public string $task_type = 'counselling';

    public string $task_priority = 'normal';

    public bool $confirm_delete = false;

    public string $delete_reason = '';

    public function mount(Tenant $tenant, Lead $lead): void
    {
        $this->authorize('view', $lead);
        $this->tenant = $tenant;
        $this->lead = $lead;
    }

    public function with(EntitlementResolver $entitlements): array
    {
        return [
            'allowed' => $entitlements->check($this->tenant, PlanFeature::LeadManagement)->isAllowed(),
            'activities' => $this->lead->activities()->with('actor')->latest('id')->limit(40)->get(),
            'notes' => $this->lead->notes()->with('author')->latest('id')->get(),
            'assignments' => $this->lead->assignments()->with(['assignee', 'assigner'])->latest('id')->get(),
            'tasks' => $this->lead->tasks()->with('assignee')->latest('id')->limit(20)->get(),
            'counsellors' => TenantMembership::query()->with('user')->where('tenant_id', $this->tenant->id)->where('role', TenantRole::Staff->value)->where('status', 'active')->get(),
            'messages' => $this->lead->conversation?->messages()->orderBy('id')->get() ?? collect(),
        ];
    }

    public function assign(LeadAssignmentService $service): void
    {
        $this->authorize('assign', $this->lead);
        $this->validate(['assign_to' => ['required', 'integer']]);
        $counsellor = User::query()->findOrFail($this->assign_to);
        $this->lead = $service->assign($this->lead, $counsellor, Auth::user(), $this->assignment_note ?: null);
        $this->reset('assign_to', 'assignment_note');
    }

    public function addNote(LeadWorkflowService $workflow): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $workflow->addNote($this->lead, Auth::user(), $this->note_body);
        $this->reset('note_body');
        $this->lead->refresh();
    }

    public function createTask(LeadTaskService $tasks): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $this->validate([
            'task_title' => ['required', 'string', 'max:255'],
            'task_due_at' => ['nullable', 'date'],
        ]);

        $tasks->createForLead($this->lead, Auth::user(), [
            'title' => $this->task_title,
            'description' => $this->task_description ?: null,
            'due_at' => $this->task_due_at ?: null,
            'task_type' => $this->task_type,
            'priority' => $this->task_priority,
            'assigned_to_user_id' => $this->lead->assigned_to,
        ], adminContext: true);

        $this->reset('task_title', 'task_description', 'task_due_at');
        $this->lead->refresh();
    }

    public function markContacted(LeadWorkflowService $workflow): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $this->lead = $workflow->markContacted($this->lead, Auth::user());
    }

    public function markLost(LeadWorkflowService $workflow): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $this->validate(['lost_reason' => ['required', 'string', 'max:500']]);
        $this->lead = $workflow->markLost($this->lead, Auth::user(), $this->lost_reason);
    }

    public function deleteLead(LeadLifecycleService $lifecycle): void
    {
        $this->authorize('delete', $this->lead);
        $lifecycle->softDelete($this->lead, Auth::user(), $this->delete_reason ?: null);
        $this->redirectRoute('tenant.leads.index', $this->tenant, navigate: true);
    }
}; ?>

<x-slot:heading>{{ $lead->public_reference }}</x-slot:heading>
<x-slot:actions>
    @can('delete', $lead)
        <flux:button wire:click="$set('confirm_delete', true)" variant="ghost" size="sm">Delete lead</flux:button>
    @endcan
</x-slot:actions>
<div class="grid gap-6">
    <div class="flex flex-wrap gap-2 border-b border-zinc-800 pb-2 text-sm">
        @foreach (['overview' => 'Overview', 'timeline' => 'Timeline', 'tasks' => 'Follow-up tasks', 'notes' => 'Notes', 'assignment' => 'Assignment', 'conversation' => 'Conversation'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['rounded-md px-3 py-1.5', 'bg-zinc-800 text-white' => $tab === $key, 'text-zinc-400' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    @if ($tab === 'overview')
        <dl class="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm sm:grid-cols-2">
            <div><dt class="text-zinc-500">Name</dt><dd>{{ $lead->full_name }}</dd></div>
            <div><dt class="text-zinc-500">Mobile</dt><dd>{{ $lead->mobile ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">Email</dt><dd>{{ $lead->email ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">Stage</dt><dd>{{ $lead->stage->label() }}</dd></div>
            <div><dt class="text-zinc-500">Assigned</dt><dd>{{ $lead->assignee?->name ?? 'Unassigned' }}</dd></div>
            <div><dt class="text-zinc-500">Next follow-up</dt><dd>{{ $lead->next_follow_up_at?->toDayDateTimeString() ?? '—' }}</dd></div>
            @if (! empty($lead->metadata['identity_matches']))
                <div class="sm:col-span-2 rounded-md border border-sky-500/20 bg-sky-500/5 px-3 py-2">
                    <dt class="text-sky-300">Identity match</dt>
                    <dd class="mt-1 text-zinc-300">This lead was matched and updated from another chat or form source.</dd>
                </div>
            @endif
            @if ($lead->enquiry_summary)<div class="sm:col-span-2"><dt class="text-zinc-500">Summary</dt><dd>{{ $lead->enquiry_summary }}</dd></div>@endif
        </dl>
        @can('manageWorkflow', $lead)
            <div class="flex flex-wrap gap-2"><flux:button wire:click="markContacted" variant="primary">Mark contacted</flux:button></div>
        @endcan
        @if ($confirm_delete)
            <div class="rounded-lg border border-red-900/50 bg-zinc-900 p-6">
                <flux:heading size="md">Delete lead</flux:heading>
                <p class="mt-2 text-sm text-zinc-400">This removes the lead from normal lists. Data is kept for audit and can be restored by a tenant admin.</p>
                <form wire:submit="deleteLead" class="mt-4 grid gap-3 max-w-xl">
                    <flux:textarea wire:model="delete_reason" label="Reason (optional)" rows="2" />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="danger">Confirm delete</flux:button>
                        <flux:button type="button" wire:click="$set('confirm_delete', false)" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            </div>
        @endif
    @endif
    @if ($tab === 'timeline')
        <ul class="grid gap-3 text-sm">
            @foreach ($activities as $activity)
                <li class="rounded-lg border border-zinc-800 bg-zinc-900 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ $activity->title ?? $activity->action_type->label() }}</span>
                        <span class="text-xs text-zinc-500">{{ $activity->created_at?->toDayDateTimeString() }}</span>
                    </div>
                    @if ($activity->description)
                        <p class="mt-1 text-zinc-400">{{ $activity->description }}</p>
                    @endif
                    @if ($activity->actor)
                        <p class="mt-1 text-xs text-zinc-500">By {{ $activity->actor->name }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($tab === 'tasks')
        @can('manageWorkflow', $lead)
            @if ($allowed)
                <form wire:submit="createTask" class="grid max-w-xl gap-3 rounded-lg border border-zinc-800 p-4">
                    <flux:input wire:model="task_title" label="Task title" required />
                    <flux:textarea wire:model="task_description" label="Description" rows="2" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:select wire:model="task_type" label="Type">
                            @foreach (LeadTaskType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="task_priority" label="Priority">
                            @foreach (LeadTaskPriority::cases() as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:input wire:model="task_due_at" type="datetime-local" label="Due at" />
                    <flux:button type="submit" variant="primary">Create follow-up task</flux:button>
                </form>
            @else
                <p class="text-sm text-amber-200">Lead management entitlement required to create tasks.</p>
            @endif
        @endcan
        <ul class="grid gap-2 text-sm">
            @foreach ($tasks as $task)
                <li class="rounded border border-zinc-800 px-3 py-2">
                    <div class="font-medium">{{ $task->title }}</div>
                    <div class="text-zinc-500">{{ $task->displayStatus()->label() }} · {{ $task->assignee?->name ?? 'Unassigned' }} · {{ $task->due_at?->toDayDateTimeString() ?? 'No due date' }}</div>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($tab === 'notes')
        <form wire:submit="addNote" class="grid gap-3"><flux:textarea wire:model="note_body" label="Internal note" rows="3" required /><flux:button type="submit" variant="primary">Add note</flux:button></form>
        <ul class="mt-4 grid gap-2 text-sm">@foreach ($notes as $note)<li class="rounded border border-zinc-800 px-3 py-2">{{ $note->author->name }} — {{ $note->created_at?->diffForHumans() }}<p class="mt-1 text-zinc-300">{{ $note->body }}</p></li>@endforeach</ul>
    @endif
    @if ($tab === 'assignment')
        @can('assign', $lead)
            <form wire:submit="assign" class="grid max-w-md gap-3 rounded-lg border border-zinc-800 p-4">
                <flux:select wire:model="assign_to" label="Counsellor" required>
                    <option value="">Select counsellor</option>
                    @foreach ($counsellors as $membership)
                        <option value="{{ $membership->user_id }}">{{ $membership->user->name }}</option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="assignment_note" label="Assignment note" rows="2" />
                <flux:button type="submit" variant="primary">Assign lead</flux:button>
            </form>
        @endcan
        <ul class="mt-4 grid gap-2 text-sm">@foreach ($assignments as $assignment)<li class="rounded border border-zinc-800 px-3 py-2">{{ $assignment->assignee->name }} assigned by {{ $assignment->assigner->name }} at {{ $assignment->assigned_at?->toDayDateTimeString() }}</li>@endforeach</ul>
    @endif
    @if ($tab === 'conversation')
        @if ($messages->isEmpty())<p class="text-zinc-500">No linked conversation messages.</p>@else
            <ul class="grid gap-2 text-sm">@foreach ($messages as $message)<li class="rounded border border-zinc-800 px-3 py-2"><span class="text-zinc-500">{{ $message->role->value }}</span><p class="mt-1">{{ $message->body }}</p></li>@endforeach</ul>
        @endif
    @endif
</div>
