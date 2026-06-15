<?php

use App\Models\Tenant;
use App\Services\Conversations\ConversationDirectoryService;
use App\Services\Leads\LeadDirectoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $leads, ConversationDirectoryService $conversations): array
    {
        return [
            'leadMetrics' => $leads->tenantMetrics($this->tenant),
            'conversationMetrics' => $conversations->tenantMetrics($this->tenant),
        ];
    }
}; ?>

<x-slot:heading>Tenant dashboard</x-slot:heading>
<div class="grid gap-6">
    <flux:heading size="lg">{{ $tenant->name }}</flux:heading>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'New leads (month)' => $leadMetrics['new_leads'],
            'Unassigned leads' => $leadMetrics['unassigned'],
            'Follow-ups due' => $leadMetrics['follow_ups_due'],
            'Waiting handoffs' => $conversationMetrics['waiting_handoffs'],
            'Active human chats' => $conversationMetrics['active_human'],
            'Unread visitor msgs' => $conversationMetrics['unread_visitor_messages'],
        ] as $label => $value)
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs text-zinc-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-3">
        <flux:button href="{{ route('tenant.leads.index', $tenant) }}" wire:navigate>Leads</flux:button>
        <flux:button href="{{ route('tenant.conversations.index', $tenant) }}" wire:navigate variant="ghost">Conversations</flux:button>
        <flux:button href="{{ route('tenant.counsellors.index', $tenant) }}" wire:navigate variant="ghost">Counsellors</flux:button>
    </div>
</div>
