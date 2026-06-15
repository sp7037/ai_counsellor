@php
    $items = [
        ['label' => 'Overview', 'route' => 'platform.overview'],
        ['label' => 'Tenants', 'route' => 'platform.tenants.index'],
        ['label' => 'Plans', 'route' => 'platform.plans.index'],
        ['label' => 'AI Operations', 'route' => 'platform.ai-operations.index'],
        ['label' => 'Usage', 'route' => 'platform.usage.index'],
        ['label' => 'Audit Logs', 'route' => 'platform.audit-logs.index'],
        ['label' => 'Platform Settings', 'route' => 'platform.settings.index'],
        ['label' => 'Failed Runs', 'route' => 'platform.failed-runs.index'],
        ['label' => 'System Health', 'route' => 'platform.system-health.index'],
    ];
@endphp

<nav class="grid gap-1 text-sm">
    <a href="{{ route('platform.overview') }}" class="mb-3 text-base font-semibold text-white" wire:navigate>{{ config('app.name') }}</a>
    @foreach ($items as $item)
        <a
            href="{{ route($item['route']) }}"
            wire:navigate
            @class([
                'rounded-md px-3 py-2 transition',
                'bg-zinc-800 text-white' => request()->routeIs($item['route'].'*') || request()->routeIs($item['route']),
                'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs($item['route'].'*') && ! request()->routeIs($item['route']),
            ])
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
