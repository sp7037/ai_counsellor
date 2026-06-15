<?php

use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        return [
            'tenants' => Tenant::query()->latest()->get(),
        ];
    }
}; ?>

<x-slot:heading>Platform — Tenants</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.create') }}" wire:navigate variant="primary" size="sm">Create tenant</flux:button>
</x-slot:actions>

<div class="overflow-hidden rounded-lg border border-zinc-800">
    <table class="min-w-full divide-y divide-zinc-800 text-sm">
        <thead class="bg-zinc-900 text-left text-zinc-400">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Slug</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-800 bg-zinc-950 text-zinc-200">
            @foreach ($tenants as $tenant)
                <tr>
                    <td class="px-4 py-3">{{ $tenant->name }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ $tenant->slug }}</td>
                    <td class="px-4 py-3">{{ $tenant->status->label() }}</td>
                    <td class="px-4 py-3 text-right">
                        <flux:button href="{{ route('platform.tenants.show', $tenant) }}" wire:navigate size="sm" variant="ghost">View</flux:button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
