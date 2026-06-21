<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\SubscriptionLifecycleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public Tenant $tenant;

    public ?int $plan_id = null;

    public string $reason = '';

    public bool $show_advanced = false;

    public function mount(Tenant $tenant): void
    {
        Gate::authorize('view', $tenant);
        $this->tenant = $tenant->load('subscription.plan');
        $this->plan_id = $this->tenant->subscription?->plan_id;
    }

    public function with(EntitlementResolver $entitlements): array
    {
        $subscription = $entitlements->subscriptionFor($this->tenant)?->load([
            'plan.entitlements',
            'events' => fn ($q) => $q->latest('created_at')->limit(15),
        ]);

        return [
            'subscription' => $subscription,
            'effectiveStatus' => $subscription?->effectiveStatus(),
            'hasOperationalAccess' => $subscription !== null
                && $subscription->effectiveStatus()->allowsOperationalAccess(),
            'plans' => Plan::query()->where('status', 'active')->orderBy('display_order')->get(),
            'usage' => $entitlements->usageSummary($this->tenant),
        ];
    }

    public function assignTrial(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validateAssignmentRules();

        if ($this->tenant->subscription !== null) {
            $this->addError('subscription', 'Tenant already has a subscription. Use change plan or cancel first.');

            return;
        }

        $plan = Plan::query()->findOrFail($this->plan_id);
        $lifecycle->startTrial($this->tenant, $plan, auth()->user(), reason: $this->reason);

        $this->afterMutation('Trial subscription assigned.');
    }

    public function assignPlan(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validateAssignmentRules();

        $plan = Plan::query()->findOrFail($this->plan_id);
        $subscription = $this->tenant->subscription()->first();

        if ($subscription === null) {
            $lifecycle->assignPlan($this->tenant, $plan, auth()->user(), $this->reason);
            $this->afterMutation('Subscription assigned and activated.');

            return;
        }

        if ($subscription->plan_id !== $plan->id) {
            $lifecycle->changePlan($subscription, $plan, auth()->user(), $this->reason);
            $subscription = $subscription->fresh();
        }

        if ($subscription->status->isTerminal()) {
            $lifecycle->activate($subscription, auth()->user(), reason: $this->reason);
        }

        $this->afterMutation('Subscription plan updated.');
    }

    public function changePlan(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validateAssignmentRules();

        $subscription = $this->tenant->subscription;

        if ($subscription === null) {
            $this->addError('subscription', 'No subscription exists. Assign a plan first.');

            return;
        }

        $plan = Plan::query()->findOrFail($this->plan_id);

        if ($subscription->plan_id === $plan->id) {
            $this->addError('plan_id', 'Select a different plan to change the subscription.');

            return;
        }

        $lifecycle->changePlan($subscription, $plan, auth()->user(), $this->reason);
        $this->afterMutation('Subscription plan changed.');
    }

    public function cancelSubscription(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);

        $subscription = $this->tenant->subscription;

        if ($subscription === null) {
            $this->addError('subscription', 'No subscription to cancel.');

            return;
        }

        $lifecycle->cancelImmediately($subscription, auth()->user(), $this->reason);
        $this->afterMutation('Subscription cancelled.');
    }

    public function activate(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);

        $subscription = $this->tenant->subscription;

        if ($subscription === null) {
            $this->addError('subscription', 'No subscription to activate.');

            return;
        }

        $lifecycle->activate($subscription, auth()->user(), reason: $this->reason);
        $this->afterMutation('Subscription activated.');
    }

    public function enterGrace(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->enterGrace($this->tenant->subscription, auth()->user(), reason: $this->reason);
        $this->afterMutation('Grace period applied.');
    }

    public function expire(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->expire($this->tenant->subscription, auth()->user(), $this->reason);
        $this->afterMutation('Subscription expired.');
    }

    public function restore(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->restore($this->tenant->subscription, auth()->user(), $this->reason);
        $this->afterMutation('Subscription restored.');
    }

    private function validateAssignmentRules(): void
    {
        $this->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
    }

    private function afterMutation(string $message): void
    {
        $this->tenant->refresh()->load('subscription.plan');
        $this->plan_id = $this->tenant->subscription?->plan_id;
        $this->reset('reason');
        session()->flash('status', $message);
    }
}; ?>

<x-slot:heading>{{ $tenant->name }} — Subscription</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate variant="ghost" size="sm">Back to tenant</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    @if (session('status'))
        <x-subscription-notice variant="info">{{ session('status') }}</x-subscription-notice>
    @endif

    @error('subscription')
        <x-subscription-notice variant="danger">{{ $message }}</x-subscription-notice>
    @enderror

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="grid gap-4">
            <flux:heading size="md">Current subscription</flux:heading>

            @if ($subscription)
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500">Status</dt>
                        <dd class="font-medium text-white">{{ $effectiveStatus?->label() ?? $subscription->status->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Plan</dt>
                        <dd class="font-medium text-white">{{ $subscription->plan->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Billing interval</dt>
                        <dd>{{ ucfirst($subscription->plan->billing_interval) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Source</dt>
                        <dd>{{ $subscription->source->label() }}</dd>
                    </div>
                    @if ($subscription->current_period_started_at)
                        <div>
                            <dt class="text-zinc-500">Period started</dt>
                            <dd>{{ $subscription->current_period_started_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->trial_ends_at)
                        <div>
                            <dt class="text-zinc-500">Trial ends</dt>
                            <dd>{{ $subscription->trial_ends_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->current_period_ends_at)
                        <div>
                            <dt class="text-zinc-500">Period ends</dt>
                            <dd>{{ $subscription->current_period_ends_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->cancelled_at)
                        <div>
                            <dt class="text-zinc-500">Cancelled</dt>
                            <dd>{{ $subscription->cancelled_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if ($subscription->expired_at)
                        <div>
                            <dt class="text-zinc-500">Expired</dt>
                            <dd>{{ $subscription->expired_at->format('d M Y') }}</dd>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">Operational entitlements</dt>
                        <dd>
                            @if ($hasOperationalAccess)
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">Restricted</flux:badge>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($usage !== [])
                    <div class="mt-2 grid gap-2 border-t border-zinc-800 pt-4 text-sm">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Usage this period</p>
                        @foreach ($usage as $row)
                            <div class="flex justify-between text-zinc-300">
                                <span>{{ $row['label'] }}</span>
                                <span>{{ $row['used'] }} / {{ $row['limit'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <flux:text class="text-zinc-400">No subscription assigned. Assign a trial or plan below.</flux:text>
            @endif
        </flux:card>

        <flux:card class="grid gap-4">
            <flux:heading size="md">
                @if ($subscription)
                    Manage subscription
                @else
                    Assign subscription
                @endif
            </flux:heading>

            <flux:select wire:model="plan_id" label="Plan">
                <option value="">Select plan</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }} ({{ ucfirst($plan->billing_interval) }})</option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="reason" label="Reason (required for audit trail)" rows="3" />

            <div class="flex flex-wrap gap-2">
                @if (! $subscription)
                    <flux:button wire:click="assignTrial" size="sm" variant="primary">Assign trial</flux:button>
                    <flux:button wire:click="assignPlan" size="sm">Assign plan</flux:button>
                @else
                    <flux:button wire:click="changePlan" size="sm" variant="primary">Change plan</flux:button>
                    <flux:button wire:click="cancelSubscription" size="sm" variant="danger">Cancel subscription</flux:button>
                    @if ($subscription->status->isTerminal())
                        <flux:button wire:click="assignPlan" size="sm">Reactivate with plan</flux:button>
                    @endif
                @endif
            </div>

            @if ($subscription)
                <div class="border-t border-zinc-800 pt-4">
                    <button type="button" wire:click="$toggle('show_advanced')" class="text-sm text-zinc-400 underline">
                        {{ $show_advanced ? 'Hide' : 'Show' }} advanced lifecycle actions
                    </button>

                    @if ($show_advanced)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <flux:button wire:click="activate" size="sm">Activate / renew</flux:button>
                            <flux:button wire:click="enterGrace" size="sm">Enter grace</flux:button>
                            <flux:button wire:click="expire" size="sm" variant="danger">Expire</flux:button>
                            <flux:button wire:click="restore" size="sm">Restore</flux:button>
                        </div>
                    @endif
                </div>
            @endif

            <flux:text class="text-xs text-zinc-500">
                Manual assignments do not create payment records. Use tenant self-serve checkout for Razorpay billing.
            </flux:text>
        </flux:card>
    </div>

    @if ($subscription && $subscription->events?->isNotEmpty())
        <flux:card>
            <flux:heading size="md" class="mb-3">Subscription history</flux:heading>
            <ul class="grid gap-2 text-sm text-zinc-300">
                @foreach ($subscription->events as $event)
                    <li>
                        {{ $event->event_type->label() }}
                        — {{ $event->created_at?->format('d M Y H:i') }}
                        @if ($event->reason)
                            <span class="text-zinc-500">({{ $event->reason }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif
</div>
