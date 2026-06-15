<?php

use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewAny', [\App\Models\TenantMessagingIntegration::class, $tenant]);
        $this->tenant = $tenant;
    }
}; ?>

<x-slot:heading>Integrations — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-4 md:grid-cols-2">
    <flux:card class="grid gap-3 p-4">
        <flux:heading size="md">WhatsApp Business</flux:heading>
        <p class="text-sm text-zinc-400">Connect your WhatsApp Business Cloud API account for inbound and outbound messaging.</p>
        <flux:button href="{{ route('tenant.integrations.whatsapp', $tenant) }}" wire:navigate variant="primary" size="sm">Configure WhatsApp</flux:button>
    </flux:card>
</div>
