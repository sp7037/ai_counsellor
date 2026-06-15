<?php

use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public string $name = '';

    public string $slug = '';

    public ?string $legal_name = null;

    public ?string $email = null;

    public ?string $phone = null;

    public bool $create_owner = true;

    public string $owner_name = '';

    public string $owner_email = '';

    public string $owner_password = '';

    public function updatedName(string $value, TenantLifecycleService $service): void
    {
        if ($this->slug === '' || $this->slug === $service->generateSlug($this->name)) {
            $this->slug = $service->generateSlug($value);
        }
    }

    public function save(TenantLifecycleService $service): void
    {
        Gate::authorize('create', Tenant::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->create_owner) {
            $rules += [
                'owner_name' => ['required', 'string', 'max:255'],
                'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'owner_password' => ['required', 'string', 'min:12'],
            ];
        }

        $this->validate($rules);

        $owner = $this->create_owner
            ? $service->createOwnerUser($this->owner_name, $this->owner_email, $this->owner_password)
            : null;

        $tenant = $service->createTenant(
            $this->only('name', 'slug', 'legal_name', 'email', 'phone'),
            $owner,
            auth()->user(),
        );

        $this->redirect(route('platform.tenants.show', $tenant), navigate: true);
    }
}; ?>

<x-slot:heading>Create tenant</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('platform.tenants.index') }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<form wire:submit="save" class="grid max-w-2xl gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
    <flux:input wire:model.live="name" label="Organisation name" required />
    <flux:input wire:model="slug" label="Slug" required />
    <flux:input wire:model="legal_name" label="Legal name" />
    <flux:input wire:model="email" label="Contact email" type="email" />
    <flux:input wire:model="phone" label="Phone" />

    <flux:separator />

    <flux:checkbox wire:model.live="create_owner" label="Create initial tenant owner account" />

    @if ($create_owner)
        <flux:input wire:model="owner_name" label="Owner name" required />
        <flux:input wire:model="owner_email" label="Owner email" type="email" required />
        <flux:input wire:model="owner_password" label="Owner password" type="password" required />
    @endif

    <flux:button type="submit" variant="primary">Create tenant</flux:button>
</form>
