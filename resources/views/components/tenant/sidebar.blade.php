@php
    $tenant = $tenant ?? request()->route('tenant');
    $items = [
        ['label' => 'Dashboard', 'route' => 'tenant.dashboard', 'pattern' => 'tenant.dashboard'],
        ['label' => 'Subscription', 'route' => 'tenant.subscription', 'pattern' => 'tenant.subscription*'],
        ['label' => 'Leads', 'route' => 'tenant.leads.index', 'pattern' => 'tenant.leads.*'],
        ['label' => 'Counsellors', 'route' => 'tenant.counsellors.index', 'pattern' => 'tenant.counsellors.*'],
        ['label' => 'Conversations', 'route' => 'tenant.conversations.index', 'pattern' => 'tenant.conversations.*'],
        ['label' => 'Knowledge', 'route' => 'tenant.knowledge.index', 'pattern' => 'tenant.knowledge.*'],
        ['label' => 'Configuration', 'route' => 'tenant.configuration.index', 'pattern' => 'tenant.configuration.*'],
        ['label' => 'AI Settings', 'route' => 'tenant.ai.configuration', 'pattern' => 'tenant.ai.*'],
        ['label' => 'Members', 'route' => 'tenant.members.index', 'pattern' => 'tenant.members.*'],
        ['label' => 'Widget', 'route' => 'tenant.widget.index', 'pattern' => 'tenant.widget.*'],
        ['label' => 'Integrations', 'route' => 'tenant.integrations.index', 'pattern' => 'tenant.integrations.*'],
    ];
@endphp
<nav class="grid gap-1 text-sm">
    <div class="mb-4 border-b border-zinc-800 pb-4">
        <x-app-logo :href="route('tenant.dashboard', $tenant)" size="sidebar" />
        <p class="mt-3 text-sm font-medium text-white">{{ $tenant->name }}</p>
        <p class="text-xs text-zinc-500">Tenant workspace</p>
    </div>
    @foreach ($items as $item)
        <a
            href="{{ route($item['route'], $tenant) }}"
            wire:navigate
            @class([
                'rounded-md px-3 py-2 transition',
                'bg-zinc-800 text-white' => request()->routeIs($item['pattern']),
                'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs($item['pattern']),
            ])
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
