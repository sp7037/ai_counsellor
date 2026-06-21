<?php

use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewTenantKnowledge', $tenant);
        $this->tenant = $tenant;
    }
}; ?>

<x-slot:heading>Knowledge base — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-4 md:grid-cols-2">
    <flux:button href="{{ route('tenant.knowledge.items', $tenant) }}" wire:navigate class="justify-start">Knowledge items</flux:button>
    <flux:button href="{{ route('tenant.knowledge.fees', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Fees</flux:button>
    <flux:button href="{{ route('tenant.knowledge.eligibility', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Eligibility rules</flux:button>
    <flux:button href="{{ route('tenant.knowledge.documents', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Source documents</flux:button>
    <flux:button href="{{ route('tenant.knowledge.course-institutions', $tenant) }}" wire:navigate class="justify-start" variant="ghost">Course availability</flux:button>
</div>
