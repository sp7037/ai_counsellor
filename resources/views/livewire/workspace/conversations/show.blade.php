<?php

use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\Conversations\ConversationHandoffService;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Conversations\ConversationReadStateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.workspace')] class extends Component {
    public Tenant $tenant;

    public Conversation $conversation;

    public string $message_body = '';

    public function mount(Tenant $tenant, Conversation $conversation): void
    {
        $this->authorize('view', $conversation);
        $this->tenant = $tenant;
        $this->conversation = $conversation->load(['lead', 'humanOwner', 'messages' => fn ($q) => $q->orderBy('id')->limit(100)]);
        app(ConversationReadStateService::class)->markReadForCounsellor($conversation, Auth::user());
    }

    public function claim(ConversationHandoffService $handoff): void
    {
        $this->authorize('claim', $this->conversation);
        $this->conversation = $handoff->claim($this->conversation, Auth::user());
        $this->conversation->load(['messages' => fn ($q) => $q->orderBy('id')->limit(100)]);
    }

    public function send(ConversationMessageService $messages): void
    {
        $this->authorize('sendAsCounsellor', $this->conversation);
        $this->validate(['message_body' => ['required', 'string', 'max:4000']]);
        $messages->sendCounsellorMessage($this->conversation, Auth::user(), $this->message_body, (string) Str::uuid());
        $this->reset('message_body');
        $this->conversation->load(['messages' => fn ($q) => $q->orderBy('id')->limit(100)]);
    }

    public function release(ConversationHandoffService $handoff): void
    {
        $this->authorize('sendAsCounsellor', $this->conversation);
        $this->conversation = $handoff->release($this->conversation, Auth::user(), 'Counsellor ended session', true);
    }

    public function refreshConversation(): void
    {
        $this->conversation->refresh()->load(['messages' => fn ($q) => $q->orderBy('id')->limit(100)]);
        app(ConversationReadStateService::class)->markReadForCounsellor($this->conversation, Auth::user());
    }
}; ?>

<div wire:poll.5s="refreshConversation">
<x-slot:heading>Conversation</x-slot:heading>
<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-800 px-4 py-3 text-sm">
            <div>
                <p class="font-medium text-white">{{ $conversation->lead?->full_name ?? 'Visitor' }}</p>
                <p class="text-zinc-500">{{ $conversation->mode->label() }} · {{ Str::limit($conversation->uuid, 8, '') }}</p>
            </div>
            <div class="flex gap-2">
                @can('claim', $conversation)
                    <flux:button wire:click="claim" size="sm" variant="primary">Accept</flux:button>
                @endcan
                @can('sendAsCounsellor', $conversation)
                    <flux:button wire:click="release" size="sm" variant="ghost">Return to AI</flux:button>
                @endcan
            </div>
        </div>
        <div class="grid max-h-[28rem] gap-3 overflow-y-auto px-4 py-3">
            @foreach ($conversation->messages as $message)
                <div @class([
                    'max-w-[85%] rounded-lg px-3 py-2 text-sm',
                    'justify-self-end bg-sky-700 text-white' => $message->role->value === 'visitor',
                    'justify-self-start bg-zinc-800 text-zinc-100' => in_array($message->role->value, ['assistant', 'system'], true),
                    'justify-self-start border border-emerald-800 bg-emerald-950 text-emerald-100' => $message->role->value === 'counsellor',
                ])>
                    <p class="mb-1 text-xs opacity-70">{{ $message->role->label() }}@if($message->sender_display_name) · {{ $message->sender_display_name }}@endif</p>
                    <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                </div>
            @endforeach
        </div>
        @can('sendAsCounsellor', $conversation)
            <form wire:submit="send" class="grid gap-2 border-t border-zinc-800 p-4">
                <flux:textarea wire:model="message_body" label="Reply" rows="3" required />
                <flux:button type="submit" variant="primary">Send</flux:button>
            </form>
        @else
            <p class="border-t border-zinc-800 p-4 text-sm text-zinc-500">Accept this conversation to reply.</p>
        @endcan
    </div>
    <aside class="rounded-lg border border-zinc-800 bg-zinc-900 p-4 text-sm">
        <flux:heading size="sm">Lead context</flux:heading>
        @if ($conversation->lead)
            <dl class="mt-3 grid gap-2">
                <div><dt class="text-zinc-500">Reference</dt><dd>{{ $conversation->lead->public_reference }}</dd></div>
                <div><dt class="text-zinc-500">Stage</dt><dd>{{ $conversation->lead->stage->label() }}</dd></div>
                <div><dt class="text-zinc-500">Score</dt><dd>{{ $conversation->lead->lead_score }}</dd></div>
                @if ($conversation->lead->mobile || $conversation->lead->email)
                    <div><dt class="text-zinc-500">Contact</dt><dd>{{ collect([$conversation->lead->mobile, $conversation->lead->email])->filter()->implode(' · ') }}</dd></div>
                @endif
                @if ($conversation->lead->programme_interest || $conversation->lead->country)
                    <div><dt class="text-zinc-500">Interests</dt><dd>{{ collect([$conversation->lead->programme_interest, $conversation->lead->country])->filter()->implode(' · ') }}</dd></div>
                @endif
                @if ($conversation->lead->ai_suggested_summary)
                    <div><dt class="text-zinc-500">Handoff summary</dt><dd class="whitespace-pre-wrap text-zinc-300">{{ $conversation->lead->ai_suggested_summary }}</dd></div>
                @endif
            </dl>
            <flux:button href="{{ route('workspace.leads.show', [$tenant, $conversation->lead]) }}" wire:navigate class="mt-4" size="sm" variant="ghost">Open lead</flux:button>
        @else
            <p class="mt-3 text-zinc-500">No linked lead yet.</p>
        @endif
    </aside>
</div>
</div>
