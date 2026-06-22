<?php

use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Enums\Knowledge\KnowledgeItemType;
use App\Models\KnowledgeItem;
use App\Models\Tenant;
use App\Services\Knowledge\KnowledgeItemService;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    use WithPagination;

    public Tenant $tenant;

    public string $type = 'faq';

    public string $title = '';

    public string $body = '';

    public string $search = '';

    public string $statusFilter = '';

    public ?string $selectedUuid = null;

    public string $editTitle = '';

    public string $editBody = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [KnowledgeItem::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $term = trim($this->search);

        $items = KnowledgeItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('title', 'like', $like)
                        ->orWhere('draft_title', 'like', $like)
                        ->orWhere('draft_body', 'like', $like);
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('updated_at')
            ->paginate(10);

        return [
            'items' => $items,
            'types' => KnowledgeItemType::cases(),
            'statuses' => KnowledgeItemStatus::cases(),
            'selected' => $this->selectedUuid
                ? KnowledgeItem::query()
                    ->where('tenant_id', $this->tenant->id)
                    ->where('uuid', $this->selectedUuid)
                    ->with('versions')
                    ->first()
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
        $this->resetPage();
        $this->selectedUuid = $item->uuid;
        $this->editTitle = $item->draft_title ?? '';
        $this->editBody = $item->draft_body ?? '';
        $this->type = $item->type->value;
    }

    public function select(string $uuid): void
    {
        $item = KnowledgeItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('uuid', $uuid)
            ->with('currentVersion')
            ->first();

        if ($item === null) {
            abort(404);
        }

        $this->authorize('view', $item);
        $this->selectedUuid = $uuid;
        $this->editTitle = $item->draft_title ?? $item->title;
        $this->editBody = $item->draft_body
            ?? $item->currentVersion?->body
            ?? '';
        $this->type = $item->type->value;
    }

    public function deselect(): void
    {
        $this->reset('selectedUuid', 'editTitle', 'editBody');
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

    public function publishItem(string $uuid, KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('publish', $item);
        $service->publish($item, auth()->user());

        if ($this->selectedUuid === $uuid) {
            $fresh = $item->fresh();
            $this->editTitle = $fresh->draft_title ?? $fresh->title;
            $this->editBody = $fresh->draft_body ?? '';
        }
    }

    public function archive(KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $this->selectedUuid)->firstOrFail();
        $this->authorize('archive', $item);
        $service->archive($item, auth()->user());
    }

    public function deleteItem(string $uuid, KnowledgeItemService $service): void
    {
        $item = KnowledgeItem::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $item);
        $service->deleteItem($item, auth()->user());

        if ($this->selectedUuid === $uuid) {
            $this->reset('selectedUuid', 'editTitle', 'editBody');
        }
    }
}; ?>

<x-slot:heading>Knowledge items</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="grid gap-3">
        @if ($selected)
            @can('update', $selected)
                <div wire:key="edit-{{ $selected->uuid }}" class="grid gap-3 rounded border border-zinc-700 bg-zinc-900/40 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading size="sm">Edit — {{ $selected->status->label() }}</flux:heading>
                        <flux:button wire:click="deselect" size="sm" variant="ghost">New item</flux:button>
                    </div>
                    @if ($selected->status !== KnowledgeItemStatus::Archived)
                        <flux:select wire:model="type" label="Type" disabled>
                            @foreach ($types as $typeCase)
                                <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="editTitle" label="Title" required />
                        <flux:textarea wire:model="editBody" label="Content" rows="8" required />
                        <div class="flex flex-wrap gap-2">
                            <flux:button wire:click="saveDraft" variant="primary">Save changes</flux:button>
                            @if (in_array($selected->status, [KnowledgeItemStatus::Draft, KnowledgeItemStatus::UnderReview], true))
                                <flux:button wire:click="publish" wire:confirm="Publish this content so the assistant can use it?">Publish</flux:button>
                            @endif
                            @if ($selected->status === KnowledgeItemStatus::Published)
                                <flux:button wire:click="publish" wire:confirm="Publish a new version of this content?">Republish</flux:button>
                                <flux:button wire:click="archive" wire:confirm="Archive this item?" variant="ghost">Archive</flux:button>
                            @endif
                            <flux:button wire:click="deleteItem('{{ $selected->uuid }}')" wire:confirm="Delete this item permanently?" variant="danger">Delete</flux:button>
                        </div>
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
                    @else
                        <p class="text-zinc-500">This item is archived and cannot be edited.</p>
                    @endif
                </div>
            @endcan
        @elseif (auth()->user()->can('create', [App\Models\KnowledgeItem::class, $tenant]))
            <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4">
                <flux:heading size="sm">Create knowledge item</flux:heading>
                <flux:select wire:model="type" label="Type">
                    @foreach ($types as $typeCase)
                        <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="title" label="Title" required />
                <flux:textarea wire:model="body" label="Content" rows="8" required />
                <flux:button type="submit" variant="primary">Create draft</flux:button>
            </form>
        @endif
    </div>

    <div class="grid gap-3">
        <div class="flex flex-col gap-2 sm:flex-row">
            <flux:input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Search title or content"
                class="flex-1"
            />
            <flux:select wire:model.live="statusFilter" class="sm:w-44">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                @endforeach
            </flux:select>
        </div>

        @forelse ($items as $item)
            <div class="rounded border border-zinc-800 p-4 text-sm {{ $selectedUuid === $item->uuid ? 'border-zinc-500' : '' }}">
                <button type="button" wire:click="select('{{ $item->uuid }}')" class="block w-full min-w-0 text-left hover:opacity-90">
                    <div class="font-medium text-white">{{ $item->title }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-zinc-400">
                        <span>{{ $item->type->label() }}</span>
                        <span aria-hidden="true">·</span>
                        @php
                            $statusBadge = match ($item->status) {
                                KnowledgeItemStatus::Published => 'bg-emerald-500/15 text-emerald-300',
                                KnowledgeItemStatus::Archived => 'bg-zinc-600/25 text-zinc-300',
                                default => 'bg-amber-500/15 text-amber-300',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge }}">{{ $item->status->label() }}</span>
                    </div>
                </button>
                <div class="mt-3 flex flex-wrap gap-2">
                    @can('update', $item)
                        <flux:button wire:click="select('{{ $item->uuid }}')" size="sm" variant="ghost">Edit</flux:button>
                    @endcan
                    @if (in_array($item->status, [KnowledgeItemStatus::Draft, KnowledgeItemStatus::UnderReview], true))
                        @can('publish', $item)
                            <flux:button wire:click="publishItem('{{ $item->uuid }}')" wire:confirm="Publish this item so the assistant can use it?" size="sm" variant="primary">Publish</flux:button>
                        @endcan
                    @endif
                    @can('delete', $item)
                        <flux:button wire:click="deleteItem('{{ $item->uuid }}')" wire:confirm="Delete this item permanently?" size="sm" variant="danger">Delete</flux:button>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-zinc-500">{{ trim($search) !== '' || $statusFilter !== '' ? 'No knowledge items match your filters.' : 'No knowledge items yet.' }}</p>
        @endforelse

        @if ($items->hasPages())
            <div class="pt-2">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
