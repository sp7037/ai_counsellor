<?php

use App\Models\Tenant;
use App\Models\TenantNote;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public string $title = '';

    public string $body = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [TenantNote::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'notes' => TenantNote::query()->latest()->get(),
        ];
    }

    public function save(): void
    {
        $this->authorize('create', [TenantNote::class, $this->tenant]);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        TenantNote::query()->create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        $this->reset('title', 'body');
    }

    public function deleteNote(int $noteId): void
    {
        $note = TenantNote::query()->find($noteId);

        if ($note === null) {
            abort(404);
        }

        $this->authorize('delete', $note);
        $note->delete();
    }
}; ?>

<x-slot:heading>Tenant notes — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    <form wire:submit="save" class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <flux:input wire:model="title" label="Title" required />
        <flux:textarea wire:model="body" label="Body" rows="3" />
        <flux:button type="submit" variant="primary">Add note</flux:button>
    </form>

    <div class="grid gap-3">
        @forelse ($notes as $note)
            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-medium text-white">{{ $note->title }}</div>
                        @if ($note->body)
                            <p class="mt-1 text-sm text-zinc-400">{{ $note->body }}</p>
                        @endif
                    </div>
                    <flux:button wire:click="deleteNote({{ $note->id }})" variant="danger" size="sm">Delete</flux:button>
                </div>
            </div>
        @empty
            <p class="text-zinc-500">No notes yet.</p>
        @endforelse
    </div>
</div>
