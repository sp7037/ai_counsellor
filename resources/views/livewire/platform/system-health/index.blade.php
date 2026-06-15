<?php

use App\Services\Platform\PlatformSystemHealthService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public function with(PlatformSystemHealthService $health): array
    {
        return ['checks' => $health->checks()];
    }
}; ?>

<x-slot:heading>System health</x-slot:heading>

<div class="grid gap-3 max-w-2xl">
    @foreach ($checks as $check)
        <div class="flex items-start justify-between rounded-lg border border-zinc-800 bg-zinc-900 p-4 text-sm">
            <div>
                <p class="font-medium text-white">{{ $check['name'] }}</p>
                <p class="mt-1 text-zinc-400">{{ $check['detail'] }}</p>
            </div>
            <span @class([
                'rounded px-2 py-0.5 text-xs uppercase',
                'bg-green-900/40 text-green-300' => $check['status'] === 'ok',
                'bg-amber-900/40 text-amber-300' => $check['status'] === 'warning',
                'bg-red-900/40 text-red-300' => $check['status'] === 'error',
            ])>{{ $check['status'] }}</span>
        </div>
    @endforeach
</div>
