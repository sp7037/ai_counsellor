<?php

use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\Course;
use App\Models\CourseInstitution;
use App\Models\Institution;
use App\Models\Tenant;
use App\Services\Knowledge\CourseInstitutionService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public ?int $courseId = null;

    public ?int $institutionId = null;

    public string $intakeLabel = '';

    public ?int $feeAmountMinor = null;

    public string $currency = 'INR';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [CourseInstitution::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return [
            'records' => CourseInstitution::query()->with(['course', 'institution'])->orderByDesc('updated_at')->get(),
            'courses' => Course::query()->orderBy('name')->get(),
            'institutions' => Institution::query()->orderBy('name')->get(),
        ];
    }

    public function create(CourseInstitutionService $service): void
    {
        $this->authorize('create', [CourseInstitution::class, $this->tenant]);
        $validated = $this->validate([
            'courseId' => ['required', 'integer'],
            'institutionId' => ['required', 'integer'],
            'intakeLabel' => ['nullable', 'string', 'max:120'],
            'feeAmountMinor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $service->create($this->tenant, [
            'course_id' => $validated['courseId'],
            'institution_id' => $validated['institutionId'],
            'intake_label' => $validated['intakeLabel'],
            'fee_amount_minor' => $validated['feeAmountMinor'],
            'currency' => $validated['currency'],
        ], auth()->user());

        $this->reset('courseId', 'institutionId', 'intakeLabel', 'feeAmountMinor');
    }

    public function publish(int $id, CourseInstitutionService $service): void
    {
        $record = CourseInstitution::query()->whereKey($id)->firstOrFail();
        $this->authorize('publish', $record);
        $service->publish($record, auth()->user());
    }

    public function archive(int $id, CourseInstitutionService $service): void
    {
        $record = CourseInstitution::query()->whereKey($id)->firstOrFail();
        $this->authorize('archive', $record);
        $service->archive($record, auth()->user());
    }
}; ?>

<x-slot:heading>Course availability</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\CourseInstitution::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4 md:grid-cols-2">
            <flux:select wire:model="courseId" label="Course">
                <option value="">Select course</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->name }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="institutionId" label="Institution">
                <option value="">Select institution</option>
                @foreach ($institutions as $institution)
                    <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="intakeLabel" label="Intake label" />
            <flux:input wire:model="feeAmountMinor" type="number" label="Fee (minor units)" min="0" />
            <flux:input wire:model="currency" label="Currency" maxlength="3" class="md:col-span-2" />
            <flux:button type="submit" variant="primary" class="md:col-span-2">Add link</flux:button>
        </form>
    @endcan

    <div class="grid gap-3">
        @forelse ($records as $record)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $record->course?->name }} @ {{ $record->institution?->name }}</div>
                    <div class="text-zinc-500">{{ $record->status->value }} @if ($record->intake_label)· {{ $record->intake_label }}@endif</div>
                </div>
                @can('update', $record)
                    <div class="flex gap-2">
                        @if ($record->status !== KnowledgePublishableStatus::Published)
                            <flux:button wire:click="publish({{ $record->id }})" size="sm">Publish</flux:button>
                        @endif
                        @if ($record->status !== KnowledgePublishableStatus::Archived)
                            <flux:button wire:click="archive({{ $record->id }})" size="sm" variant="ghost">Archive</flux:button>
                        @endif
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-zinc-500">No course-institution links yet.</p>
        @endforelse
    </div>
</div>
