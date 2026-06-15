<?php

use App\Enums\Leads\LeadStage;
use App\Enums\Tenancy\TenantRole;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Leads\LeadAssignmentService;
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

    public function mount(Tenant $tenant, Lead $lead): void
    {
        $this->authorize('view', $lead);
        $this->tenant = $tenant;
        $this->lead = $lead;
    }

    public function with(): array
    {
        return [
            'activities' => $this->lead->activities()->with('actor')->latest('id')->limit(30)->get(),
            'notes' => $this->lead->notes()->with('author')->latest('id')->get(),
            'assignments' => $this->lead->assignments()->with(['assignee', 'assigner'])->latest('id')->get(),
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
}; ?>

<x-slot:heading>{{ $lead->public_reference }}</x-slot:heading>
<div class="grid gap-6">
    <div class="flex flex-wrap gap-2 border-b border-zinc-800 pb-2 text-sm">
        @foreach (['overview' => 'Overview', 'activity' => 'Activity', 'notes' => 'Notes', 'assignment' => 'Assignment', 'conversation' => 'Conversation'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['rounded-md px-3 py-1.5', 'bg-zinc-800 text-white' => $tab === $key, 'text-zinc-400' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    @if ($tab === 'overview')
        <dl class="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm sm:grid-cols-2">
            <div><dt class="text-zinc-500">Name</dt><dd>{{ $lead->full_name }}</dd></div>
            <div><dt class="text-zinc-500">Mobile</dt><dd>{{ $lead->mobile ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">Email</dt><dd>{{ $lead->email ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">Stage</dt><dd>{{ $lead->stage->label() }}</dd></div>
            <div><dt class="text-zinc-500">Qualification</dt><dd>{{ $lead->qualification_status->label() }}</dd></div>
            <div><dt class="text-zinc-500">Score</dt><dd>{{ $lead->lead_score }} (advisory)</dd></div>
            <div><dt class="text-zinc-500">Source</dt><dd>{{ $lead->source->label() }}</dd></div>
            <div><dt class="text-zinc-500">Assigned</dt><dd>{{ $lead->assignee?->name ?? 'Unassigned' }}</dd></div>
            @if ($lead->enquiry_summary)<div class="sm:col-span-2"><dt class="text-zinc-500">Summary</dt><dd>{{ $lead->enquiry_summary }}</dd></div>@endif
        </dl>
        @can('manageWorkflow', $lead)
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="markContacted" variant="primary">Mark contacted</flux:button>
            </div>
        @endcan
    @endif
    @if ($tab === 'activity')
        <ul class="grid gap-2 text-sm">@foreach ($activities as $activity)<li class="rounded border border-zinc-800 px-3 py-2">{{ $activity->created_at?->toDayDateTimeString() }} — {{ $activity->action_type->label() }} @if($activity->actor) by {{ $activity->actor->name }} @endif</li>@endforeach</ul>
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
