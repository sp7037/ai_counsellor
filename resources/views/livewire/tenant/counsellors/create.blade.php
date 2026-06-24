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

    public function with(CounsellorManagementService $service): array
    {
        return [
            'provisioningBlockReason' => $service->provisioningBlockReason($this->tenant),
        ];
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

        session()->flash('status', 'Counsellor account created successfully.');

        $this->redirect(route('tenant.counsellors.index', $this->tenant), navigate: true);
    }
}; ?>

<x-slot:heading>Add counsellor</x-slot:heading>

<div class="grid max-w-2xl gap-4">
    @if ($provisioningBlockReason)
        <flux:callout variant="warning">
            {{ $provisioningBlockReason }}
        </flux:callout>
    @endif

    @error('form')
        <flux:callout variant="warning">{{ $message }}</flux:callout>
    @enderror

    <form wire:submit="save" class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-6 text-zinc-100 [&_label]:text-zinc-200 [&_.text-red-500]:text-red-300">
        <p class="text-sm text-zinc-400">Create a counsellor login for workspace handoff and live responses.</p>

        <flux:input wire:model="name" label="Full name" required :disabled="(bool) $provisioningBlockReason" />
        <flux:input wire:model="email" label="Email" type="email" required :disabled="(bool) $provisioningBlockReason" />
        <flux:input wire:model="password" label="Temporary password" type="password" required :disabled="(bool) $provisioningBlockReason" />
        <flux:input wire:model="mobile" label="Mobile" :disabled="(bool) $provisioningBlockReason" />
        <flux:input wire:model="designation" label="Designation" :disabled="(bool) $provisioningBlockReason" />

        <p class="text-xs text-zinc-500">Counsellor profile remains tenant-scoped automatically. The email must be unique across the platform and is not linked to any other organisation.</p>

        <flux:button type="submit" variant="primary" :disabled="(bool) $provisioningBlockReason">Create counsellor</flux:button>
    </form>
</div>
