<?php

use App\Enums\Conversations\ConversationMode;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\Conversations\ConversationDirectoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
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
            'conversations' => $directory->paginateForTenantAdmin(
                $this->tenant,
                array_filter(['mode' => $this->mode ?: null]),
            ),
        ];
    }
}; ?>

<x-slot:heading>Conversations</x-slot:heading>
<div class="grid gap-4">
    <div class="flex flex-wrap gap-2">
        @foreach (['' => 'All', 'ai' => 'AI', 'handoff_requested' => 'Waiting', 'human' => 'Human', 'closed' => 'Closed'] as $value => $label)
            <flux:button wire:click="$set('mode', '{{ $value }}')" size="sm" :variant="$mode === '{{ $value }}' ? 'primary' : 'ghost'">{{ $label }}</flux:button>
        @endforeach
    </div>
    <div class="overflow-hidden rounded-lg border border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-800 text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-400"><tr>
                <th class="px-4 py-3">Contact</th><th class="px-4 py-3">Channel</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Counsellor</th><th class="px-4 py-3">Waiting</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-zinc-800 bg-zinc-950">
                @forelse ($conversations as $conversation)
                    <tr>
                        <td class="px-4 py-3">{{ $conversation->lead?->full_name ?? 'Visitor' }}</td>
                        <td class="px-4 py-3">{{ $conversation->channel->label() }}</td>
                        <td class="px-4 py-3">{{ $conversation->mode->label() }}</td>
                        <td class="px-4 py-3">{{ $conversation->humanOwner?->name ?? $conversation->targetCounsellor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $conversation->handoff_requested_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><flux:button href="{{ route('tenant.conversations.show', [$tenant, $conversation]) }}" wire:navigate size="sm" variant="ghost">Open</flux:button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No conversations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $conversations->links() }}
</div>
