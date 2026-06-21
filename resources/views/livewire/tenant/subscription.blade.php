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
            'payments' => \App\Models\Payment::query()
                ->where('tenant_id', $this->tenant->id)
                ->with(['paymentOrder.plan'])
                ->latest()
                ->limit(10)
                ->get(),
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
<x-slot:actions>
    <flux:button href="{{ route('tenant.subscription.plans', $tenant) }}" wire:navigate size="sm">View plans</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @if (session('subscription_restriction'))
        <x-subscription-notice>
            Your access to some features is restricted. Review your subscription status below.
        </x-subscription-notice>
    @endif

    @if ($subscription === null)
        <x-tenant.panel>
            <h2 class="text-lg font-semibold text-white">No active subscription</h2>
            <p class="mt-2 text-sm text-zinc-300">Contact your platform administrator to assign a plan or start a trial.</p>
        </x-tenant.panel>
    @else
        <x-tenant.panel class="grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-white">{{ $subscription->plan->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-400">{{ $subscription->plan->description }}</p>
                </div>
                <flux:badge>{{ $effectiveStatus?->label() ?? $subscription->status->label() }}</flux:badge>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                @if ($subscription->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at)
                    <div>
                        <dt class="text-zinc-500">Trial ends</dt>
                        <dd class="font-medium text-zinc-100">{{ $subscription->trial_ends_at->format('d M Y H:i') }}</dd>
                    </div>
                @endif
                @if ($subscription->current_period_ends_at)
                    <div>
                        <dt class="text-zinc-500">Current period ends</dt>
                        <dd class="font-medium text-zinc-100">{{ $subscription->current_period_ends_at->format('d M Y H:i') }}</dd>
                    </div>
                @endif
                @if ($subscription->grace_ends_at)
                    <div>
                        <dt class="text-zinc-500">Grace period ends</dt>
                        <dd class="font-medium text-amber-300">{{ $subscription->grace_ends_at->format('d M Y H:i') }}</dd>
                    </div>
                @endif
                @if ($subscription->cancel_at_period_end)
                    <div>
                        <dt class="text-zinc-500">Cancellation</dt>
                        <dd class="font-medium text-zinc-100">Scheduled at period end</dd>
                    </div>
                @endif
            </dl>

            @if ($effectiveStatus === SubscriptionStatus::Grace)
                <x-subscription-notice>
                    Your subscription is in a grace period. Renew soon to avoid interruption.
                </x-subscription-notice>
            @elseif (in_array($effectiveStatus, [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled, SubscriptionStatus::PastDue], true))
                <x-subscription-notice variant="danger">
                    Your subscription is not active. Renew online or contact support.
                </x-subscription-notice>
            @endif
        </x-tenant.panel>

        @if ($payments->isNotEmpty())
            <x-tenant.panel heading="Payment history">
                <ul class="grid gap-2 text-sm">
                    @foreach ($payments as $payment)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-800 py-2 text-zinc-200 first:border-t-0">
                            <div>
                                <span class="text-white">{{ $payment->paymentOrder->plan->name ?? 'Plan' }}</span>
                                <span class="text-zinc-500"> — {{ $payment->captured_at?->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm">{{ $payment->status->label() }}</flux:badge>
                                <span class="text-zinc-300">{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</span>
                                <flux:button href="{{ route('tenant.subscription.payment.receipt', [$tenant, $payment]) }}" wire:navigate size="xs" variant="ghost">Receipt</flux:button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-tenant.panel>
        @endif

        @if ($usage !== [])
            <x-tenant.panel heading="Usage this period">
                <div class="grid gap-4">
                    @foreach ($usage as $row)
                        <div>
                            <div class="mb-1 flex justify-between text-sm text-zinc-200">
                                <span>{{ $row['label'] }}</span>
                                <span class="font-medium text-white">{{ $row['used'] }} / {{ $row['limit'] }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-zinc-800">
                                <div class="h-2 rounded-full bg-sky-500" style="width: {{ min(100, $row['limit'] > 0 ? round(($row['used'] / $row['limit']) * 100) : 0) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-tenant.panel>
        @endif

        <x-tenant.panel heading="Included capabilities">
            <ul class="grid gap-2 text-sm">
                @foreach ($features as $feature)
                    <li class="flex items-center justify-between text-zinc-200">
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
        </x-tenant.panel>

        @if ($events->isNotEmpty())
            <x-tenant.panel heading="Recent subscription events">
                <ul class="grid gap-2 text-sm text-zinc-300">
                    @foreach ($events as $event)
                        <li>{{ $event->event_type->label() }} — {{ $event->created_at?->format('d M Y H:i') }}</li>
                    @endforeach
                </ul>
            </x-tenant.panel>
        @endif
    @endif
</div>
