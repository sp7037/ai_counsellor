<?php

use App\Enums\Configuration\CatalogueStatus;
use App\Enums\Configuration\StudyMode;
use App\Models\Course;
use App\Models\Tenant;
use App\Services\Configuration\TenantCatalogueService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public string $name = '';

    public string $description = '';

    public string $duration = '';

    public string $studyMode = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [Course::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'items' => Course::query()->orderBy('sort_order')->orderBy('name')->get(),
            'studyModes' => StudyMode::cases(),
        ];
    }

    public function create(TenantCatalogueService $service): void
    {
        $this->authorize('create', [Course::class, $this->tenant]);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:120'],
            'studyMode' => ['nullable', 'in:'.implode(',', array_column(StudyMode::cases(), 'value'))],
        ]);
        $service->createCourse($this->tenant, $validated + ['study_mode' => $validated['studyMode'] ?: null], auth()->user());
        $this->reset('name', 'description', 'duration', 'studyMode');
    }

    public function toggleStatus(string $uuid, TenantCatalogueService $service): void
    {
        $item = Course::query()->where('uuid', $uuid)->first() ?? abort(404);
        $this->authorize('update', $item);
        $status = $item->status === CatalogueStatus::Active ? CatalogueStatus::Inactive : CatalogueStatus::Active;
        $service->setCourseStatus($item, $status, auth()->user());
    }

    public function deleteItem(string $uuid, TenantCatalogueService $service): void
    {
        $item = Course::query()->where('uuid', $uuid)->first() ?? abort(404);
        $this->authorize('delete', $item);
        $service->removeCourse($item, auth()->user());
    }
}; ?>

<x-slot:heading>Courses</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\Course::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:input wire:model="name" label="Course name" required />
            <flux:textarea wire:model="description" label="Description" rows="2" />
            <flux:input wire:model="duration" label="Duration" />
            <flux:select wire:model="studyMode" label="Study mode">
                <option value="">Select mode</option>
                @foreach ($studyModes as $mode)
                    <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">Add course</flux:button>
        </form>
    @endcan
    <div class="grid gap-3">
        @forelse ($items as $item)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $item->name }}</div>
                    <div class="text-zinc-500">{{ $item->status->label() }} @if($item->study_mode) · {{ $item->study_mode->label() }} @endif</div>
                </div>
                @can('update', $item)
                    <div class="flex gap-2">
                        <flux:button wire:click="toggleStatus('{{ $item->uuid }}')" size="sm" variant="ghost">Toggle</flux:button>
                        <flux:button wire:click="deleteItem('{{ $item->uuid }}')" size="sm" variant="danger">Remove</flux:button>
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-zinc-500">No courses configured yet.</p>
        @endforelse
    </div>
</div>
