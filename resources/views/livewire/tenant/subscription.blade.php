<?php

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Tenant;
use App\Services\Billing\EntitlementResolver;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function with(EntitlementResolver $entitlements): array
    {
        $subscription = $entitlements->subscriptionFor($this->tenant);
        $effective = $subscription?->effectiveStatus();

        return [
            'subscription' => $subscription?->load('plan.entitlements'),
            'effectiveStatus' => $effective,
            'usage' => $entitlements->usageSummary($this->tenant),
            'events' => $subscription?->events()->latest('created_at')->limit(10)->get() ?? collect(),
            'features' => $subscription
                ? $subscription->plan->entitlements->map(fn ($e) => [
                    'code' => $e->feature->value,
                    'label' => $e->feature->label(),
                    'enabled' => $e->enabled,
                    'limit' => $e->isUnlimited() ? null : $e->limit_value,
                ])
                : collect(),
        ];
    }
}; ?>

<x-slot:heading>Subscription</x-slot:heading>

<div class="grid gap-6">
    @if (session('subscription_restriction'))
        <flux:callout variant="warning">
            Your access to some features is restricted. Review your subscription status below.
        </flux:callout>
    @endif

    @if ($subscription === null)
        <flux:card>
            <flux:heading size="lg">No active subscription</flux:heading>
            <flux:text class="mt-2 text-zinc-400">Contact your platform administrator to assign a plan or start a trial.</flux:text>
        </flux:card>
    @else
        <flux:card class="grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ $subscription->plan->name }}</flux:heading>
                    <flux:text class="text-zinc-400">{{ $subscription->plan->description }}</flux:text>
                </div>
                <flux:badge>{{ $effectiveStatus?->label() ?? $subscription->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                @if ($subscription->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at)
                    <div><dt class="text-zinc-500">Trial ends</dt><dd>{{ $subscription->trial_ends_at->format('d M Y H:i') }}</dd></div>
                @endif
                @if ($subscription->current_period_ends_at)
                    <div><dt class="text-zinc-500">Current period ends</dt><dd>{{ $subscription->current_period_ends_at->format('d M Y H:i') }}</dd></div>
                @endif
                @if ($subscription->grace_ends_at)
                    <div><dt class="text-zinc-500">Grace period ends</dt><dd class="text-amber-400">{{ $subscription->grace_ends_at->format('d M Y H:i') }}</dd></div>
                @endif
                @if ($subscription->cancel_at_period_end)
                    <div><dt class="text-zinc-500">Cancellation</dt><dd>Scheduled at period end</dd></div>
                @endif
            </dl>

            @if ($effectiveStatus === SubscriptionStatus::Grace)
                <flux:callout variant="warning">Your subscription is in a grace period. Renew soon to avoid interruption.</flux:callout>
            @elseif (in_array($effectiveStatus, [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled, SubscriptionStatus::PastDue], true))
                <flux:callout variant="danger">Your subscription is not active. Contact support to restore access.</flux:callout>
            @endif
        </flux:card>

        @if ($usage !== [])
            <flux:card>
                <flux:heading size="md" class="mb-4">Usage this period</flux:heading>
                <div class="grid gap-4">
                    @foreach ($usage as $key => $row)
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span>{{ $row['label'] }}</span>
                                <span>{{ $row['used'] }} / {{ $row['limit'] }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-zinc-800">
                                <div class="h-2 rounded-full bg-sky-500" style="width: {{ min(100, $row['limit'] > 0 ? round(($row['used'] / $row['limit']) * 100) : 0) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        <flux:card>
            <flux:heading size="md" class="mb-4">Included capabilities</flux:heading>
            <ul class="grid gap-2 text-sm">
                @foreach ($features as $feature)
                    <li class="flex items-center justify-between">
                        <span>{{ $feature['label'] }}</span>
                        @if ($feature['enabled'])
                            <flux:badge size="sm" color="green">
                                Included
                                @if ($feature['limit'] !== null)
                                    ({{ $feature['limit'] }})
                                @endif
                            </flux:badge>
                        @else
                            <flux:badge size="sm">Not included</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </flux:card>

        @if ($events->isNotEmpty())
            <flux:card>
                <flux:heading size="md" class="mb-4">Recent subscription events</flux:heading>
                <ul class="grid gap-2 text-sm text-zinc-300">
                    @foreach ($events as $event)
                        <li>{{ $event->event_type->label() }} — {{ $event->created_at?->format('d M Y H:i') }}</li>
                    @endforeach
                </ul>
            </flux:card>
        @endif
    @endif
</div>
