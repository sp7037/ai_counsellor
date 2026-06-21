<?php

use App\Models\Conversation;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Conversation::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'conversations' => Conversation::query()->with('messages')->latest('last_message_at')->latest()->limit(50)->get(),
        ];
    }
}; ?>

<x-slot:heading>Widget conversations — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.widget.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Widget settings</flux:button>
</x-slot:actions>

<div class="grid gap-4">
    @forelse ($conversations as $conversation)
        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 text-sm">
            <div class="flex justify-between gap-4">
                <div>
                    <div class="font-medium text-white font-mono text-xs">{{ $conversation->uuid }}</div>
                    <div class="mt-1 text-zinc-400">Origin: {{ $conversation->origin_domain ?? 'unknown' }}</div>
                    <div class="text-zinc-500">Status: {{ $conversation->status->label() }} · Messages: {{ $conversation->messages->count() }}</div>
                </div>
                <div class="text-zinc-500">{{ $conversation->started_at?->diffForHumans() }}</div>
            </div>
        </div>
    @empty
        <p class="text-zinc-500">No widget conversations yet.</p>
    @endforelse
</div>
