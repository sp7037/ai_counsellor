<?php

use App\Models\Tenant;
use App\Services\Conversations\ConversationDirectoryService;
use App\Services\Leads\LeadDirectoryService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
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

<x-slot:heading>Dashboard</x-slot:heading>

<div class="grid gap-6">
    <div>
        <h2 class="text-xl font-semibold text-white">{{ $tenant->name }}</h2>
        <p class="mt-1 text-sm text-zinc-400">Overview of leads and conversations for your organisation.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'New leads (month)' => $leadMetrics['new_leads'],
            'Unassigned leads' => $leadMetrics['unassigned'],
            'Follow-ups due' => $leadMetrics['follow_ups_due'],
            'Waiting handoffs' => $conversationMetrics['waiting_handoffs'],
            'Active human chats' => $conversationMetrics['active_human'],
            'Unread visitor msgs' => $conversationMetrics['unread_visitor_messages'],
        ] as $label => $value)
            <x-tenant.stat-card :label="$label" :value="$value" />
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:button href="{{ route('tenant.leads.index', $tenant) }}" wire:navigate>Leads</flux:button>
        <flux:button href="{{ route('tenant.conversations.index', $tenant) }}" wire:navigate variant="ghost">Conversations</flux:button>
        <flux:button href="{{ route('tenant.counsellors.index', $tenant) }}" wire:navigate variant="ghost">Counsellors</flux:button>
        <flux:button href="{{ route('tenant.subscription', $tenant) }}" wire:navigate variant="ghost">Subscription</flux:button>
    </div>
</div>
