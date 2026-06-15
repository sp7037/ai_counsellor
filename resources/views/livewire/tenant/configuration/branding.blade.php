<?php

use App\Enums\Configuration\WidgetPosition;
use App\Models\Tenant;
use App\Services\Configuration\TenantBrandingService;
use App\Services\Configuration\TenantConfigurationResolver;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public Tenant $tenant;

    public string $displayName = '';

    public string $primaryColor = '#2563EB';

    public ?string $accentColor = null;

    public string $widgetPosition = 'bottom_right';

    public string $timezone = 'Asia/Kolkata';

    public string $locale = 'en';

    public $logoUpload = null;

    public function mount(Tenant $tenant, TenantConfigurationResolver $resolver): void
    {
        $this->authorize('viewTenantConfiguration', $tenant);
        $this->tenant = $tenant;

        $settings = $resolver->settings($tenant);
        $widgetSettings = $resolver->widgetSettings($tenant);

        $this->displayName = (string) ($settings->display_name ?? $tenant->name);
        $this->primaryColor = (string) $settings->primary_color;
        $this->accentColor = $settings->accent_color;
        $this->widgetPosition = $widgetSettings->widget_position?->value ?? WidgetPosition::BottomRight->value;
        $this->timezone = (string) ($tenant->timezone ?? 'Asia/Kolkata');
        $this->locale = (string) ($tenant->locale ?? 'en');
    }

    public function with(TenantConfigurationResolver $resolver): array
    {
        $settings = $resolver->settings($this->tenant);

        return [
            'logoUrl' => $settings->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) : null,
            'positions' => WidgetPosition::cases(),
            'timezones' => ['Asia/Kolkata', 'UTC', 'Asia/Dubai', 'Europe/London'],
            'locales' => config('configuration.supported_locales', ['en']),
        ];
    }

    public function save(TenantBrandingService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);

        $this->validate([
            'displayName' => ['required', 'string', 'max:120'],
            'primaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentColor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'widgetPosition' => ['required', 'in:bottom_right,bottom_left'],
            'timezone' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'string', 'max:12'],
        ]);

        $service->update($this->tenant, [
            'display_name' => $this->displayName,
            'primary_color' => $this->primaryColor,
            'accent_color' => $this->accentColor,
            'widget_position' => $this->widgetPosition,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
        ], auth()->user());
    }

    public function uploadLogo(TenantBrandingService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);

        $this->validate([
            'logoUpload' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $service->uploadLogo($this->tenant, $this->logoUpload, auth()->user());
        $this->reset('logoUpload');
    }

    public function removeLogo(TenantBrandingService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);
        $service->removeLogo($this->tenant, auth()->user());
    }
}; ?>

<x-slot:heading>Branding</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid max-w-2xl gap-6">
    @if ($logoUrl)
        <div class="rounded border border-zinc-800 p-4">
            <img src="{{ $logoUrl }}" alt="Tenant logo" class="max-h-16">
            @can('manageTenantConfiguration', $tenant)
                <flux:button wire:click="removeLogo" class="mt-3" variant="danger" size="sm">Remove logo</flux:button>
            @endcan
        </div>
    @endif

    @can('manageTenantConfiguration', $tenant)
        <form wire:submit="uploadLogo" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:input type="file" wire:model="logoUpload" label="Upload logo (JPEG, PNG, WebP, max 2 MB)" />
            <flux:button type="submit" variant="ghost" size="sm">Upload logo</flux:button>
        </form>

        <form wire:submit="save" class="grid gap-4 rounded border border-zinc-800 bg-zinc-900 p-4">
            <flux:input wire:model="displayName" label="Display name" required />
            <flux:input wire:model="primaryColor" label="Primary colour" required />
            <flux:input wire:model="accentColor" label="Accent colour" />
            <flux:select wire:model="widgetPosition" label="Widget position">
                @foreach ($positions as $position)
                    <option value="{{ $position->value }}">{{ $position->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="timezone" label="Timezone">
                @foreach ($timezones as $tz)
                    <option value="{{ $tz }}">{{ $tz }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="locale" label="Default locale">
                @foreach ($locales as $code)
                    <option value="{{ $code }}">{{ strtoupper($code) }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">Save branding</flux:button>
        </form>
    @else
        <p class="text-zinc-500">You can view branding settings but cannot change them.</p>
    @endcan
</div>
