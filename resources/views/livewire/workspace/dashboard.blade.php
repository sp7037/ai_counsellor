<?php

use App\Models\Tenant;
use App\Services\Conversations\ConversationDirectoryService;
use App\Services\Leads\LeadDirectoryService;
use App\Services\Leads\LeadTaskDirectoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(LeadDirectoryService $leads, ConversationDirectoryService $conversations, LeadTaskDirectoryService $tasks): array
    {
        $user = Auth::user();

        return [
            'leadMetrics' => $leads->counsellorMetrics($this->tenant, $user),
            'taskMetrics' => $tasks->counsellorCounts($this->tenant, $user),
            'conversationMetrics' => $conversations->counsellorMetrics($this->tenant, $user),
        ];
    }
}; ?>

<x-slot:heading>My dashboard</x-slot:heading>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
        'Assigned open' => $leadMetrics['assigned_open'],
        'Follow-ups today' => $taskMetrics['today'],
        'Overdue tasks' => $taskMetrics['overdue'],
        'Pending tasks' => $taskMetrics['pending'],
        'Waiting chats' => $conversationMetrics['waiting_assigned'],
        'Active chats' => $conversationMetrics['active_conversations'],
    ] as $label => $value)
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs text-zinc-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>
    @endforeach
</div>
