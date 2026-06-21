<?php

use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\EligibilityRule;
use App\Models\Tenant;
use App\Services\Knowledge\EligibilityRuleService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $title = '';

    public string $requiredCriteria = '';

    public string $preferredCriteria = '';

    public int $priority = 100;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [EligibilityRule::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function with(): array
    {
        return ['rules' => EligibilityRule::query()->orderBy('priority')->get()];
    }

    public function create(EligibilityRuleService $service): void
    {
        $this->authorize('create', [EligibilityRule::class, $this->tenant]);
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'requiredCriteria' => ['nullable', 'string', 'max:4000'],
            'preferredCriteria' => ['nullable', 'string', 'max:4000'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $service->create($this->tenant, [
            'title' => $validated['title'],
            'required_criteria' => $validated['requiredCriteria'],
            'preferred_criteria' => $validated['preferredCriteria'],
            'priority' => $validated['priority'],
        ], auth()->user());

        $this->reset('title', 'requiredCriteria', 'preferredCriteria');
    }

    public function publish(string $uuid, EligibilityRuleService $service): void
    {
        $rule = EligibilityRule::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('publish', $rule);
        $service->publish($rule, auth()->user());
    }

    public function archive(string $uuid, EligibilityRuleService $service): void
    {
        $rule = EligibilityRule::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('archive', $rule);
        $service->archive($rule, auth()->user());
    }
}; ?>

<x-slot:heading>Eligibility rules</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.knowledge.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @can('create', [App\Models\EligibilityRule::class, $tenant])
        <form wire:submit="create" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:input wire:model="title" label="Title" required />
            <flux:textarea wire:model="requiredCriteria" label="Required criteria" rows="3" />
            <flux:textarea wire:model="preferredCriteria" label="Preferred criteria" rows="3" />
            <flux:input wire:model="priority" type="number" label="Priority" min="1" max="999" />
            <flux:button type="submit" variant="primary">Add rule</flux:button>
        </form>
    @endcan

    <div class="grid gap-3">
        @forelse ($rules as $rule)
            <div class="flex items-start justify-between rounded border border-zinc-800 p-4 text-sm">
                <div>
                    <div class="font-medium text-white">{{ $rule->title }}</div>
                    <div class="text-zinc-500">Priority {{ $rule->priority }} · {{ $rule->status->value }}</div>
                    @if ($rule->required_criteria)<p class="mt-1 text-zinc-400">Required: {{ $rule->required_criteria }}</p>@endif
                </div>
                @can('update', $rule)
                    <div class="flex gap-2">
                        @if ($rule->status !== KnowledgePublishableStatus::Published)
                            <flux:button wire:click="publish('{{ $rule->uuid }}')" size="sm">Publish</flux:button>
                        @endif
                        @if ($rule->status !== KnowledgePublishableStatus::Archived)
                            <flux:button wire:click="archive('{{ $rule->uuid }}')" size="sm" variant="ghost">Archive</flux:button>
                        @endif
                    </div>
                @endcan
            </div>
        @empty
            <p class="text-zinc-500">No eligibility rules yet.</p>
        @endforelse
    </div>
</div>
