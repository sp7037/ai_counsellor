@php
    $items = [
        ['label' => 'Overview', 'route' => 'platform.overview'],
        ['label' => 'Tenants', 'route' => 'platform.tenants.index'],
        ['label' => 'Plan requests', 'route' => 'platform.plan-change-requests.index'],
        ['label' => 'Account lookup', 'route' => 'platform.account-lookup'],
        ['label' => 'Plans', 'route' => 'platform.plans.index'],
        ['label' => 'Payments', 'route' => 'platform.payments.index'],
        ['label' => 'Payment orders', 'route' => 'platform.payment-orders.index'],
        ['label' => 'Integrations', 'route' => 'platform.integrations.index'],
        ['label' => 'AI Operations', 'route' => 'platform.ai-operations.index'],
        ['label' => 'Usage', 'route' => 'platform.usage.index'],
        ['label' => 'Audit Logs', 'route' => 'platform.audit-logs.index'],
        ['label' => 'Platform Settings', 'route' => 'platform.settings.index'],
        ['label' => 'Failed Runs', 'route' => 'platform.failed-runs.index'],
        ['label' => 'System Health', 'route' => 'platform.system-health.index'],
    ];
@endphp

<nav class="grid gap-1 text-sm">
    <div class="mb-4 border-b border-zinc-800 pb-4">
        <x-app-logo :href="route('platform.overview')" size="sidebar" />
        <p class="mt-2 text-xs uppercase tracking-wide text-zinc-500">Platform Super Admin</p>
    </div>
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
