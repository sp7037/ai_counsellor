<?php

use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewTenantConfiguration', $tenant);
        $this->tenant = $tenant;
    }
}; ?>

<x-slot:heading>Configuration — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-4 md:grid-cols-2">
    <flux:button href="{{ route('tenant.configuration.branding', $tenant) }}" wire:navigate class="justify-start">Branding and logo</flux:button>
    <flux:button href="{{ route('tenant.configuration.assistant', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Assistant, languages and messages</flux:button>
    <flux:button href="{{ route('tenant.configuration.office-hours', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Office hours</flux:button>
    <flux:button href="{{ route('tenant.configuration.services', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Services</flux:button>
    <flux:button href="{{ route('tenant.configuration.courses', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Courses</flux:button>
    <flux:button href="{{ route('tenant.configuration.institutions', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Institutions</flux:button>
    <flux:button href="{{ route('tenant.configuration.locations', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Locations</flux:button>
    <flux:button href="{{ route('tenant.widget.index', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Widget keys and domains</flux:button>
</div>
