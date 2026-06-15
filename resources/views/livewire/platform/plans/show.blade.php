<?php

use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\PlanStatus;
use App\Models\Plan;
use App\Services\Billing\PlanManagementService;
use App\Services\Billing\PlanPricingService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Plan $plan;

    public string $name = '';

    public string $description = '';

    public ?string $currency = null;

    public ?int $amount_minor = null;

    public bool $is_purchasable = false;

    public function mount(Plan $plan): void
    {
        Gate::authorize('view', $plan);
        $this->plan = $plan->load('entitlements');
        $this->name = $plan->name;
        $this->description = (string) $plan->description;
        $this->currency = $plan->currency;
        $this->amount_minor = $plan->amount_minor;
        $this->is_purchasable = (bool) $plan->is_purchasable;
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

    public function savePricing(PlanPricingService $pricing): void
    {
        Gate::authorize('update', $this->plan);

        $this->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'is_purchasable' => ['boolean'],
        ]);

        $this->plan = $pricing->updatePricing($this->plan, [
            'currency' => $this->currency,
            'amount_minor' => $this->amount_minor,
            'is_purchasable' => $this->is_purchasable,
        ], auth()->user());

        session()->flash('pricing_status', 'Pricing updated.');
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

    <flux:card class="grid gap-3">
        <flux:heading size="md">Commercial pricing</flux:heading>
        @if (session('pricing_status'))
            <flux:callout variant="success">{{ session('pricing_status') }}</flux:callout>
        @endif
        <form wire:submit="savePricing" class="grid gap-3 max-w-md">
            <flux:input wire:model="currency" label="Currency (ISO 4217)" placeholder="INR" maxlength="3" />
            <flux:input wire:model="amount_minor" label="Amount (minor units, e.g. paise)" type="number" min="0" />
            <flux:checkbox wire:model="is_purchasable" label="Publicly purchasable" />
            <flux:text class="text-xs text-zinc-500">Leave amount empty to keep the plan non-purchasable. Price changes are audited.</flux:text>
            <flux:button type="submit" size="sm">Save pricing</flux:button>
        </form>
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
