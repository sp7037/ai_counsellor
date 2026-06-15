<?php

use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\PlanStatus;
use App\Models\Plan;
use App\Services\Billing\PlanManagementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Plan $plan;

    public string $name = '';

    public string $description = '';

    public function mount(Plan $plan): void
    {
        Gate::authorize('view', $plan);
        $this->plan = $plan->load('entitlements');
        $this->name = $plan->name;
        $this->description = (string) $plan->description;
    }

    public function with(): array
    {
        return [
            'entitlements' => $this->plan->entitlements,
            'featureLabels' => collect(PlanFeature::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()]),
        ];
    }

    public function deactivate(PlanManagementService $plans): void
    {
        Gate::authorize('update', $this->plan);
        $this->plan = $plans->setStatus($this->plan, PlanStatus::Inactive, auth()->user());
    }

    public function activate(PlanManagementService $plans): void
    {
        Gate::authorize('update', $this->plan);
        $this->plan = $plans->setStatus($this->plan, PlanStatus::Active, auth()->user());
    }
}; ?>

<x-slot:heading>{{ $plan->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.plans.index') }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    <flux:card class="grid gap-3">
        <flux:heading size="md">Plan details</flux:heading>
        <flux:text>Code: {{ $plan->code }}</flux:text>
        <flux:text>Billing interval: {{ $plan->billing_interval }}</flux:text>
        <flux:text>Status: {{ $plan->status->label() }}</flux:text>
        <div class="flex gap-2">
            @if ($plan->status === PlanStatus::Active)
                <flux:button wire:click="deactivate" variant="danger" size="sm">Deactivate</flux:button>
            @else
                <flux:button wire:click="activate" size="sm">Activate</flux:button>
            @endif
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="md" class="mb-4">Entitlements</flux:heading>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-zinc-500"><th class="pb-2">Feature</th><th>Enabled</th><th>Limit</th></tr></thead>
            <tbody>
                @foreach ($entitlements as $row)
                    <tr class="border-t border-zinc-800">
                        <td class="py-2">{{ $featureLabels[$row->feature->value] ?? $row->feature->value }}</td>
                        <td>{{ $row->enabled ? 'Yes' : 'No' }}</td>
                        <td>{{ $row->isUnlimited() ? 'Unlimited' : $row->limit_value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </flux:card>
</div>
