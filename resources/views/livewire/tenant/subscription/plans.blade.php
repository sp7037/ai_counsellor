<?php

use App\Enums\Billing\PlanStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Billing\PaymentCredentialsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(PaymentCredentialsService $credentials): array
    {
        return [
            'plans' => Plan::query()
                ->where('status', PlanStatus::Active)
                ->where('is_public', true)
                ->orderBy('display_order')
                ->get(),
            'paymentsEnabled' => $credentials->isEnabled(),
            'testMode' => $credentials->environment()->value === 'test',
        ];
    }
}; ?>

<x-slot:heading>Choose a plan</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.subscription', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @if ($testMode)
        <flux:callout variant="warning">Payments are running in <strong>test mode</strong>. No real money will be collected.</flux:callout>
    @endif

    @if (! $paymentsEnabled)
        <flux:callout variant="warning">Online payments are not configured yet. Contact your platform administrator.</flux:callout>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($plans as $plan)
            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading size="md">{{ $plan->name }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ $plan->description }}</flux:text>
                </div>

                @if ($plan->isPurchasable())
                    <div>
                        <p class="text-2xl font-semibold text-white">{{ $plan->formattedPrice() }}</p>
                        <p class="text-sm text-zinc-500">per {{ $plan->billing_interval }}</p>
                    </div>
                    @if ($paymentsEnabled)
                        <flux:button href="{{ route('tenant.subscription.checkout', [$tenant, $plan]) }}" wire:navigate class="mt-auto">
                            Subscribe
                        </flux:button>
                    @endif
                @else
                    <flux:badge>Pricing not configured</flux:badge>
                    <flux:text class="text-sm text-zinc-500">This plan is not available for self-service purchase.</flux:text>
                @endif
            </flux:card>
        @empty
            <flux:card>
                <flux:text>No public plans are available.</flux:text>
            </flux:card>
        @endforelse
    </div>
</div>
