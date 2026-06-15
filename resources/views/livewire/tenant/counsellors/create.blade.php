<?php

use App\Models\Tenant;
use App\Services\Leads\CounsellorManagementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $mobile = '';

    public string $designation = '';

    public function mount(Tenant $tenant): void
    {
        $this->authorize('create', [\App\Models\TenantMembership::class, $tenant]);
        $this->tenant = $tenant;
    }

    public function save(CounsellorManagementService $service): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:120'],
        ]);

        $service->create($this->tenant, $this->only('name', 'email', 'password'), $this->only('mobile', 'designation'), Auth::user());

        $this->redirect(route('tenant.counsellors.index', $this->tenant), navigate: true);
    }
}; ?>

<x-slot:heading>Add counsellor</x-slot:heading>
<form wire:submit="save" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
    <flux:input wire:model="name" label="Full name" required />
    <flux:input wire:model="email" label="Email" type="email" required />
    <flux:input wire:model="password" label="Temporary password" type="password" required />
    <flux:input wire:model="mobile" label="Mobile" />
    <flux:input wire:model="designation" label="Designation" />
    <flux:button type="submit" variant="primary">Create counsellor</flux:button>
</form>
