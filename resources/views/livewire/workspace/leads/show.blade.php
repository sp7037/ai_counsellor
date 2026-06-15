<?php

use App\Models\Lead;
use App\Models\Tenant;
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

    public function scheduleFollowUp(LeadWorkflowService $workflow): void
    {
        $this->authorize('manageWorkflow', $this->lead);
        $this->validate(['follow_up_at' => ['required', 'date']]);
        $workflow->scheduleFollowUp($this->lead, Auth::user(), new \DateTimeImmutable($this->follow_up_at));
        $this->reset('follow_up_at');
        $this->lead->refresh();
    }
}; ?>

<x-slot:heading>{{ $lead->public_reference }}</x-slot:heading>
<div class="grid gap-6">
    <dl class="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-sm sm:grid-cols-2">
        <div><dt class="text-zinc-500">Name</dt><dd>{{ $lead->full_name }}</dd></div>
        <div><dt class="text-zinc-500">Stage</dt><dd>{{ $lead->stage->label() }}</dd></div>
        <div><dt class="text-zinc-500">Mobile</dt><dd>{{ $lead->mobile ?? '—' }}</dd></div>
        <div><dt class="text-zinc-500">Next follow-up</dt><dd>{{ $lead->next_follow_up_at?->toDayDateTimeString() ?? '—' }}</dd></div>
    </dl>
    <div class="flex flex-wrap gap-2">
        <flux:button wire:click="markContactAttempt" variant="primary">Record contact attempt</flux:button>
    </div>
    <form wire:submit="scheduleFollowUp" class="grid max-w-md gap-3 rounded-lg border border-zinc-800 p-4">
        <flux:input wire:model="follow_up_at" type="datetime-local" label="Schedule follow-up" required />
        <flux:button type="submit" variant="primary">Save follow-up</flux:button>
    </form>
    <form wire:submit="addNote" class="grid gap-3"><flux:textarea wire:model="note_body" label="Internal note" rows="3" required /><flux:button type="submit">Add note</flux:button></form>
</div>
