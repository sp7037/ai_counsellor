<?php

use App\Enums\Conversations\ConversationMode;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\Conversations\ConversationDirectoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    #[Url]
    public string $mode = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Conversation::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(ConversationDirectoryService $directory): array
    {
        return [
            'conversations' => $directory->paginateForCounsellor(
                $this->tenant,
                Auth::user(),
                array_filter(['mode' => $this->mode ?: null]),
            ),
        ];
    }
}; ?>

<x-slot:heading>Conversations</x-slot:heading>
<div class="grid gap-4">
    <div class="flex flex-wrap gap-2">
        <flux:button wire:click="$set('mode', '')" size="sm" :variant="$mode === '' ? 'primary' : 'ghost'">All</flux:button>
        <flux:button wire:click="$set('mode', 'handoff_requested')" size="sm" :variant="$mode === 'handoff_requested' ? 'primary' : 'ghost'">Waiting</flux:button>
        <flux:button wire:click="$set('mode', 'human')" size="sm" :variant="$mode === 'human' ? 'primary' : 'ghost'">Active</flux:button>
        <flux:button wire:click="$set('mode', 'closed')" size="sm" :variant="$mode === 'closed' ? 'primary' : 'ghost'">Closed</flux:button>
    </div>
    <div class="overflow-hidden rounded-lg border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-400"><tr>
                <th class="px-4 py-3">Contact</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Last message</th><th class="px-4 py-3">Unread</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950">
                @forelse ($conversations as $conversation)
                    <tr>
                        <td class="px-4 py-3">{{ $conversation->lead?->full_name ?? 'Visitor' }}</td>
                        <td class="px-4 py-3">{{ $conversation->mode->label() }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $conversation->human_owner_id === auth()->id() ? $conversation->counsellor_unread_count : '—' }}</td>
                        <td class="px-4 py-3 text-right"><flux:button href="{{ route('workspace.conversations.show', [$tenant, $conversation]) }}" wire:navigate size="sm" variant="ghost">Open</flux:button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No conversations in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $conversations->links() }}
</div>
