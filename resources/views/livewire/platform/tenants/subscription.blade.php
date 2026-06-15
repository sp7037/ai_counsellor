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

    public function mount(Tenant $tenant): void
    {
        Gate::authorize('view', $tenant);
        $this->tenant = $tenant;
        $this->plan_id = $tenant->subscription?->plan_id;
    }

    public function with(EntitlementResolver $entitlements): array
    {
        return [
            'subscription' => $entitlements->subscriptionFor($this->tenant)?->load(['plan.entitlements', 'events' => fn ($q) => $q->latest('created_at')->limit(15)]),
            'plans' => Plan::query()->where('status', 'active')->orderBy('display_order')->get(),
            'usage' => $entitlements->usageSummary($this->tenant),
        ];
    }

    public function startTrial(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['plan_id' => ['required', 'exists:plans,id'], 'reason' => ['required', 'string', 'max:1000']]);
        $plan = Plan::query()->findOrFail($this->plan_id);
        $lifecycle->startTrial($this->tenant, $plan, auth()->user(), reason: $this->reason);
        $this->reset('reason');
    }

    public function activate(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $subscription = $this->tenant->subscription;

        if ($subscription === null) {
            $this->validate(['plan_id' => ['required', 'exists:plans,id'], 'reason' => ['required', 'string', 'max:1000']]);
            $plan = Plan::query()->findOrFail($this->plan_id);
            $lifecycle->assignPlan($this->tenant, $plan, auth()->user(), $this->reason);

            return;
        }

        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->activate($subscription, auth()->user(), reason: $this->reason);
        $this->reset('reason');
    }

    public function enterGrace(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->enterGrace($this->tenant->subscription, auth()->user(), reason: $this->reason);
        $this->reset('reason');
    }

    public function expire(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->expire($this->tenant->subscription, auth()->user(), $this->reason);
        $this->reset('reason');
    }

    public function restore(SubscriptionLifecycleService $lifecycle): void
    {
        Gate::authorize('manage', Subscription::class);
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $lifecycle->restore($this->tenant->subscription, auth()->user(), $this->reason);
        $this->reset('reason');
    }
}; ?>

<x-slot:heading>{{ $tenant->name }} — Subscription</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate variant="ghost" size="sm">Back to tenant</flux:button>
</x-slot:actions>

<div class="grid gap-6 lg:grid-cols-2">
    <flux:card class="grid gap-4">
        <flux:heading size="md">Current subscription</flux:heading>
        @if ($subscription)
            <flux:text>Plan: {{ $subscription->plan->name }}</flux:text>
            <flux:text>Status: {{ $subscription->effectiveStatus()->label() }}</flux:text>
            @if ($subscription->trial_ends_at)<flux:text>Trial ends: {{ $subscription->trial_ends_at->format('d M Y') }}</flux:text>@endif
            @if ($subscription->current_period_ends_at)<flux:text>Period ends: {{ $subscription->current_period_ends_at->format('d M Y') }}</flux:text>@endif
        @else
            <flux:text class="text-zinc-400">No subscription assigned.</flux:text>
        @endif

        @if ($usage !== [])
            <div class="mt-2 grid gap-2 text-sm">
                @foreach ($usage as $row)
                    <div>{{ $row['label'] }}: {{ $row['used'] }} / {{ $row['limit'] }}</div>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:card class="grid gap-4">
        <flux:heading size="md">Manage</flux:heading>
        <flux:select wire:model="plan_id" label="Plan">
            <option value="">Select plan</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
            @endforeach
        </flux:select>
        <flux:textarea wire:model="reason" label="Reason (required)" rows="3" />
        <div class="flex flex-wrap gap-2">
            @if (! $subscription)
                <flux:button wire:click="startTrial" size="sm">Start trial</flux:button>
                <flux:button wire:click="activate" size="sm" variant="primary">Assign & activate</flux:button>
            @else
                <flux:button wire:click="activate" size="sm">Activate / renew</flux:button>
                <flux:button wire:click="enterGrace" size="sm">Enter grace</flux:button>
                <flux:button wire:click="expire" size="sm" variant="danger">Expire</flux:button>
                <flux:button wire:click="restore" size="sm">Restore</flux:button>
            @endif
        </div>
    </flux:card>

    @if ($subscription && $subscription->events?->isNotEmpty())
        <flux:card class="lg:col-span-2">
            <flux:heading size="md" class="mb-3">Subscription history</flux:heading>
            <ul class="grid gap-2 text-sm">
                @foreach ($subscription->events as $event)
                    <li>{{ $event->event_type->label() }} — {{ $event->created_at?->format('d M Y H:i') }} @if($event->reason)<span class="text-zinc-500">({{ $event->reason }})</span>@endif</li>
                @endforeach
            </ul>
        </flux:card>
    @endif
</div>
