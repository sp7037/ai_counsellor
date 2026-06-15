@php
    $tenant = $tenant ?? request()->route('tenant');
    $items = [
        ['label' => 'My Dashboard', 'route' => 'workspace.dashboard'],
        ['label' => 'My Leads', 'route' => 'workspace.leads.index'],
        ['label' => 'Follow-ups', 'route' => 'workspace.follow-ups.index'],
    ];
@endphp
<nav class="grid gap-1 text-sm">
    <p class="mb-3 text-base font-semibold text-white">{{ $tenant->name }}</p>
    @foreach ($items as $item)
        <a href="{{ route($item['route'], $tenant) }}" wire:navigate @class([
            'rounded-md px-3 py-2',
            'bg-zinc-800 text-white' => request()->routeIs($item['route'].'*') || request()->routeIs($item['route']),
            'text-zinc-400 hover:bg-zinc-900 hover:text-white' => ! request()->routeIs($item['route'].'*') && ! request()->routeIs($item['route']),
        ])>{{ $item['label'] }}</a>
    @endforeach
</nav>
