<?php

use App\Models\Plan;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public function with(): array
    {
        Gate::authorize('viewAny', Plan::class);

        return [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('display_order')->get(),
        ];
    }
}; ?>

<x-slot:heading>Plans</x-slot:heading>

<div class="grid gap-4">
    @forelse ($plans as $plan)
        <flux:card class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="md">{{ $plan->name }}</flux:heading>
                <flux:text class="text-zinc-400">{{ $plan->code }} · {{ $plan->billing_interval }}</flux:text>
            </div>
            <div class="flex items-center gap-3">
                <flux:badge>{{ $plan->status->label() }}</flux:badge>
                <flux:button href="{{ route('platform.plans.show', $plan) }}" wire:navigate size="sm">Manage</flux:button>
            </div>
        </flux:card>
    @empty
        <flux:text>No plans configured. Run <code>php artisan db:seed --class=PlansSeeder</code> locally.</flux:text>
    @endforelse
</div>
