<?php

use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Enums\Knowledge\KnowledgeItemType;
use App\Models\KnowledgeItem;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeItemService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public string $type = 'faq';

    public string $title = '';

    public string $body = '';

    public ?string $selectedUuid = null;

    public string $editTitle = '';

    public string $editBody = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [KnowledgeItem::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'items' => KnowledgeItem::query()->orderByDesc('updated_at')->get(),
            'types' => KnowledgeItemType::cases(),
            'selected' => $this->selectedUuid
                ? KnowledgeItem::query()->where('uuid', $this->selectedUuid)->with('versions')->first()
                : null,
        ];
    }

    public function create(KnowledgeItemService $service): void
    {
        $this->authorize('create', [KnowledgeItem::class, $this->tenant]);
        $validated = $this->validate([
            'type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $item = $service->createDraft($this->tenant, $validated, auth()->user());
        $this->reset('title', 'body');
        $this->selectedUuid = $item->uuid;
        $this->editTitle = $item->draft_title ?? '';
        $this->editBody = $item->draft_body ?? '';
    }

    public function select(string $uuid): void
    {
        $item = KnowledgeItem::query()->where('uuid', $uuid)->first();

        if ($item === null) {
            abort(404);
        }

        $this->authorize('view', $item);
        $this->selectedUuid = $uuid;
        $this->editTitle = $item->draft_title ?? $item->title;
        $this->editBody = $item->draft_body ?? '';
    }

    public function saveDraft(KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $this->selectedUuid)->firstOrFail();
        $this->authorize('update', $item);
        $this->validate([
            'editTitle' => ['required', 'string', 'max:200'],
            'editBody' => ['required', 'string', 'max:20000'],
        ]);
        $service->updateDraft($item, [
            'draft_title' => $this->editTitle,
            'draft_body' => $this->editBody,
        ], auth()->user());
    }

    public function publish(KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $this->selectedUuid)->firstOrFail();
        $this->authorize('publish', $item);
        $service->publish($item, auth()->user());
    }

    public function archive(KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $this->selectedUuid)->firstOrFail();
        $this->authorize('archive', $item);
        $service->archive($item, auth()->user());
    }

    public function deleteItem(KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $this->selectedUuid)->firstOrFail();
        $this->authorize('delete', $item);
        $service->deleteItem($item, auth()->user());
        $this->reset('selectedUuid', 'editTitle', 'editBody');
    }
}; ?>

<x-slot:heading>Knowledge items</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6 lg:grid-cols-2">
    @can('create', [App\Models\KnowledgeItem::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:select wire:model="type" label="Type">
                @foreach ($types as $typeCase)
                    <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="title" label="Title" required />
            <flux:textarea wire:model="body" label="Content" rows="4" required />
            <flux:button type="submit" variant="primary">Create draft</flux:button>
        </form>
    @endcan

    <div class="grid gap-3">
        @forelse ($items as $item)
            <button type="button" wire:click="select('{{ $item->uuid }}')" class="rounded border border-zinc-800 p-4 text-left text-sm hover:border-zinc-600 {{ $selectedUuid === $item->uuid ? 'border-zinc-500' : '' }}">
                <div class="font-medium text-white">{{ $item->title }}</div>
                <div class="text-zinc-500">{{ $item->type->label() }} · {{ $item->status->label() }}</div>
            </button>
        @empty
            <p class="text-zinc-500">No knowledge items yet.</p>
        @endforelse
    </div>
</div>

@if ($selected)
    <div class="mt-6 grid gap-4 rounded border border-zinc-800 p-4">
        <flux:heading size="sm">Edit draft — {{ $selected->status->label() }}</flux:heading>
        @if ($selected->status !== KnowledgeItemStatus::Archived)
            <flux:input wire:model="editTitle" label="Draft title" />
            <flux:textarea wire:model="editBody" label="Draft content" rows="6" />
            @can('update', $selected)
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="saveDraft" variant="primary">Save draft</flux:button>
                    <flux:button wire:click="publish" wire:confirm="Publish this content?">Publish</flux:button>
                    @if ($selected->status === KnowledgeItemStatus::Published)
                        <flux:button wire:click="archive" wire:confirm="Archive this item?" variant="ghost">Archive</flux:button>
                    @endif
                    <flux:button wire:click="deleteItem" wire:confirm="Delete this item permanently?" variant="danger">Delete</flux:button>
                </div>
            @endcan
        @else
            <p class="text-zinc-500">This item is archived and cannot be edited.</p>
        @endif
        @if ($selected->versions->isNotEmpty())
            <div class="text-sm text-zinc-400">
                <div class="font-medium text-zinc-300">Version history</div>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($selected->versions->sortByDesc('version_number') as $version)
                        <li>v{{ $version->version_number }} — {{ $version->published_at?->toDayDateTimeString() }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
