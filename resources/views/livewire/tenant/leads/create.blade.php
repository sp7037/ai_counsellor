<?php

use App\Enums\Leads\LeadSource;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\Leads\LeadCreationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $full_name = '';

    public string $mobile = '';

    public string $email = '';

    public string $service_interest = '';

    public string $enquiry_summary = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('create', [Lead::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function save(LeadCreationService $creation): void
    {
        $this->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'service_interest' => ['nullable', 'string', 'max:255'],
            'enquiry_summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $creation->create($this->tenant, LeadSource::Manual, $this->only(
            'full_name', 'mobile', 'email', 'service_interest', 'enquiry_summary'
        ), Auth::user());

        $this->redirect(route('tenant.leads.show', [$this->tenant, $lead]), navigate: true);
    }
}; ?>

<x-slot:heading>Create lead</x-slot:heading>
<form wire:submit="save" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
    <flux:input wire:model="full_name" label="Full name" required />
    <flux:input wire:model="mobile" label="Mobile" />
    <flux:input wire:model="email" label="Email" type="email" />
    <flux:input wire:model="service_interest" label="Service interest" />
    <flux:textarea wire:model="enquiry_summary" label="Enquiry summary" rows="4" />
    <flux:button type="submit" variant="primary">Create lead</flux:button>
</form>
