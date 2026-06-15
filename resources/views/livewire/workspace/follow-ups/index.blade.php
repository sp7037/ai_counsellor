<?php

use App\Enums\Leads\FollowUpStatus;
use App\Models\LeadFollowUp;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'followUps' => LeadFollowUp::query()
                ->with('lead')
                ->where('tenant_id', $this->tenant->id)
                ->where('assigned_to', Auth::id())
                ->where('status', FollowUpStatus::Scheduled->value)
                ->orderBy('due_at')
                ->get(),
        ];
    }
}; ?>

<x-slot:heading>Follow-ups</x-slot:heading>
<ul class="grid gap-2 text-sm">
    @forelse ($followUps as $followUp)
        <li class="rounded border border-zinc-800 px-3 py-2">
            <a href="{{ route('workspace.leads.show', [$tenant, $followUp->lead]) }}" class="font-medium underline" wire:navigate>{{ $followUp->lead->public_reference }}</a>
            — due {{ $followUp->due_at->toDayDateTimeString() }}
        </li>
    @empty
        <li class="text-zinc-500">No scheduled follow-ups.</li>
    @endforelse
</ul>
