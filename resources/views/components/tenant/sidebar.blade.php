@php
    $tenant = $tenant ?? request()->route('tenant');
    $items = [
        ['label' => 'Dashboard', 'route' => 'tenant.dashboard'],
        ['label' => 'Leads', 'route' => 'tenant.leads.index'],
        ['label' => 'Counsellors', 'route' => 'tenant.counsellors.index'],
        ['label' => 'Conversations', 'route' => 'tenant.conversations.index'],
        ['label' => 'Knowledge', 'route' => 'tenant.knowledge.index'],
        ['label' => 'AI Settings', 'route' => 'tenant.ai.configuration'],
        ['label' => 'Members', 'route' => 'tenant.members.index'],
        ['label' => 'Widget', 'route' => 'tenant.widget.index'],
    ];
@endphp
<nav class="grid gap-1 text-sm">
    <a href="{{ route('tenant.dashboard', $tenant) }}" class="mb-3 text-base font-semibold text-white" wire:navigate>{{ $tenant->name }}</a>
    @foreach ($items as $item)
        <a href="{{ route($item['route'], $tenant) }}" wire:navigate @class([
            'rounded-md px-3 py-2',
            'bg-zinc-800 text-white' => request()->routeIs($item['route'].'*') || request()->routeIs($item['route']),
            'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs($item['route'].'*') && ! request()->routeIs($item['route']),
        ])>{{ $item['label'] }}</a>
    @endforeach
</nav>
