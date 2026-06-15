<?php

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Conversations\ConversationHandoffService;
use App\Services\Leads\LeadCreationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public Conversation $conversation;

    public string $convert_name = '';

    public string $convert_mobile = '';

    public string $convert_email = '';

    public ?int $assign_counsellor_id = null;

    public function mount(Tenant $tenant, Conversation $conversation): void
    {
        $this->authorize('view', $conversation);
        $this->tenant = $tenant;
        $this->conversation = $conversation->load(['lead', 'humanOwner', 'activities', 'handoffs.counsellor', 'messages' => fn ($q) => $q->orderBy('id')->limit(100)]);
    }

    public function convertToLead(LeadCreationService $creation): void
    {
        $this->authorize('convertToLead', $this->conversation);
        $this->validate([
            'convert_name' => ['required', 'string', 'max:120'],
            'convert_mobile' => ['nullable', 'string', 'max:20'],
            'convert_email' => ['nullable', 'email'],
        ]);

        $lead = $creation->fromConversation($this->conversation, [
            'full_name' => $this->convert_name,
            'mobile' => $this->convert_mobile ?: null,
            'email' => $this->convert_email ?: null,
            'enquiry_summary' => 'Converted from conversation by tenant admin.',
        ], Auth::user());

        $this->conversation->refresh()->load('lead');
        session()->flash('status', 'Lead '.$lead->public_reference.' created.');
    }

    public function assignCounsellor(ConversationHandoffService $handoff): void
    {
        $this->authorize('assign', $this->conversation);
        $this->validate(['assign_counsellor_id' => ['required', 'integer']]);
        $counsellor = User::query()->findOrFail($this->assign_counsellor_id);
        $this->conversation = $handoff->assignCounsellor($this->conversation, $counsellor, Auth::user());
    }

    public function closeConversation(ConversationHandoffService $handoff): void
    {
        $this->authorize('assign', $this->conversation);
        $this->conversation = $handoff->close($this->conversation, Auth::user(), 'Closed by tenant admin');
        session()->flash('status', 'Conversation closed.');
    }

    public function resumeAi(ConversationHandoffService $handoff): void
    {
        $this->authorize('assign', $this->conversation);
        $this->conversation = $handoff->release($this->conversation, Auth::user(), 'AI resumed by tenant admin', true);
        session()->flash('status', 'AI assistance resumed.');
    }

    public function with(): array
    {
        return [
            'counsellors' => User::query()
                ->whereHas('memberships', fn ($q) => $q->where('tenant_id', $this->tenant->id)->where('role', 'staff')->where('status', 'active'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}; ?>

<x-slot:heading>Conversation supervision</x-slot:heading>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <div class="text-sm text-zinc-400">{{ $conversation->channel->label() }} · {{ $conversation->mode->label() }} · {{ $conversation->uuid }}</div>
        <div class="grid max-h-96 gap-2 overflow-y-auto">
            @foreach ($conversation->messages as $message)
                <div class="rounded bg-zinc-950 p-2 text-sm"><span class="text-zinc-500">{{ $message->role->label() }}:</span> {{ $message->body }}</div>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button wire:click="resumeAi" size="sm" variant="ghost">Resume AI</flux:button>
            <flux:button wire:click="closeConversation" size="sm" variant="danger">Close</flux:button>
        </div>
    </div>
    <aside class="grid gap-4">
        @if ($conversation->lead)
            <div class="rounded-lg border border-zinc-800 p-4 text-sm">
                <flux:heading size="sm">Linked lead</flux:heading>
                <p class="mt-2">{{ $conversation->lead->public_reference }} — {{ $conversation->lead->full_name }}</p>
                <flux:button href="{{ route('tenant.leads.show', [$tenant, $conversation->lead]) }}" wire:navigate class="mt-3" size="sm" variant="ghost">Open lead</flux:button>
            </div>
        @else
            <form wire:submit="convertToLead" class="grid gap-3 rounded-lg border border-zinc-800 p-4">
                <flux:heading size="sm">Convert to lead</flux:heading>
                <flux:input wire:model="convert_name" label="Full name" required />
                <flux:input wire:model="convert_mobile" label="Mobile" />
                <flux:input wire:model="convert_email" label="Email" type="email" />
                <flux:button type="submit" variant="primary" size="sm">Create lead</flux:button>
            </form>
        @endif
        <form wire:submit="assignCounsellor" class="grid gap-3 rounded-lg border border-zinc-800 p-4">
            <flux:heading size="sm">Assign counsellor</flux:heading>
            <flux:select wire:model="assign_counsellor_id" label="Counsellor">
                <option value="">Select counsellor</option>
                @foreach ($counsellors as $counsellor)
                    <option value="{{ $counsellor->id }}">{{ $counsellor->name }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit" size="sm">Assign</flux:button>
        </form>
    </aside>
</div>
