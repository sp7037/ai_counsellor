<?php

use App\Enums\Configuration\WidgetPosition;
use App\Models\Tenant;
use App\Services\Configuration\TenantAssistantConfigurationService;
use App\Services\Configuration\TenantConfigurationResolver;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $assistantName = '';

    public string $assistantTitle = '';

    public string $welcomeMessage = '';

    public string $offlineMessage = '';

    public bool $offlineFormEnabled = true;

    public int $welcomeDelaySeconds = 0;

    public string $widgetPosition = 'bottom_right';

    public bool $aiDisclosureEnabled = true;

    public string $aiDisclosureMessage = '';

    public bool $humanTransferEnabled = true;

    public string $humanTransferLabel = '';

    public string $humanTransferMessage = '';

    public string $consentText = '';

    public string $consentVersion = '';

    public string $defaultLocale = 'en';

    public array $supportedLocales = ['en'];

    public function mount(Tenant $tenant, TenantConfigurationResolver $resolver): void
    {
        $this->authorize('viewTenantConfiguration', $tenant);
        $this->tenant = $tenant;

        $settings = $resolver->settings($tenant);
        $widgetSettings = $resolver->widgetSettings($tenant);

        $this->assistantName = (string) ($settings->assistant_name ?? '');
        $this->assistantTitle = (string) ($settings->assistant_title ?? '');
        $this->welcomeMessage = (string) $widgetSettings->welcome_message;
        $this->offlineMessage = (string) $widgetSettings->offline_message;
        $this->offlineFormEnabled = (bool) $widgetSettings->offline_form_enabled;
        $this->welcomeDelaySeconds = (int) $widgetSettings->welcome_delay_seconds;
        $this->widgetPosition = $widgetSettings->widget_position?->value ?? WidgetPosition::BottomRight->value;
        $this->aiDisclosureEnabled = (bool) $settings->ai_disclosure_enabled;
        $this->aiDisclosureMessage = (string) ($settings->ai_disclosure_message ?? '');
        $this->humanTransferEnabled = (bool) $settings->human_transfer_enabled;
        $this->humanTransferLabel = (string) ($settings->human_transfer_label ?? '');
        $this->humanTransferMessage = (string) ($settings->human_transfer_message ?? '');
        $this->consentText = (string) ($settings->consent_text ?? '');
        $this->consentVersion = (string) ($settings->consent_version ?? '');
        $this->defaultLocale = (string) $settings->default_locale;
        $this->supportedLocales = $settings->supported_locales ?? ['en'];
    }

    public function with(): array
    {
        return ['locales' => config('configuration.supported_locales', ['en'])];
    }

    public function save(TenantAssistantConfigurationService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);

        $this->validate([
            'assistantName' => ['required', 'string', 'max:120'],
            'welcomeMessage' => ['required', 'string', 'max:500'],
            'offlineMessage' => ['required', 'string', 'max:500'],
            'aiDisclosureMessage' => ['required', 'string', 'max:500'],
            'humanTransferLabel' => ['required', 'string', 'max:120'],
            'welcomeDelaySeconds' => ['integer', 'min:0', 'max:30'],
            'supportedLocales' => ['array', 'min:1'],
        ]);

        $service->update($this->tenant, [
            'assistant_name' => $this->assistantName,
            'assistant_title' => $this->assistantTitle,
            'welcome_message' => $this->welcomeMessage,
            'offline_message' => $this->offlineMessage,
            'offline_form_enabled' => $this->offlineFormEnabled,
            'welcome_delay_seconds' => $this->welcomeDelaySeconds,
            'widget_position' => $this->widgetPosition,
            'ai_disclosure_enabled' => $this->aiDisclosureEnabled,
            'ai_disclosure_message' => $this->aiDisclosureMessage,
            'human_transfer_enabled' => $this->humanTransferEnabled,
            'human_transfer_label' => $this->humanTransferLabel,
            'human_transfer_message' => $this->humanTransferMessage,
            'consent_text' => $this->consentText,
            'consent_version' => $this->consentVersion,
            'default_locale' => $this->defaultLocale,
            'supported_locales' => $this->supportedLocales,
        ], auth()->user());
    }
}; ?>

<x-slot:heading>Assistant and messages</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

@can('manageTenantConfiguration', $tenant)
    <form wire:submit="save" class="grid max-w-2xl gap-4 rounded border border-zinc-800 bg-zinc-900 p-4">
        <flux:input wire:model="assistantName" label="Assistant name" required />
        <flux:input wire:model="assistantTitle" label="Assistant title" />
        <flux:textarea wire:model="welcomeMessage" label="Welcome message" rows="2" required />
        <flux:textarea wire:model="offlineMessage" label="Offline message" rows="2" required />
        <flux:checkbox wire:model="offlineFormEnabled" label="Enable offline form" />
        <flux:input wire:model="welcomeDelaySeconds" type="number" min="0" max="30" label="Welcome delay (seconds)" />
        <flux:checkbox wire:model="aiDisclosureEnabled" label="Show AI disclosure" />
        <flux:textarea wire:model="aiDisclosureMessage" label="AI disclosure message" rows="2" />
        <flux:checkbox wire:model="humanTransferEnabled" label="Offer human transfer option" />
        <flux:input wire:model="humanTransferLabel" label="Human transfer button label" />
        <flux:textarea wire:model="humanTransferMessage" label="Human transfer helper text" rows="2" />
        <flux:textarea wire:model="consentText" label="Consent text" rows="3" />
        <flux:input wire:model="consentVersion" label="Consent version" />
        <flux:select wire:model="defaultLocale" label="Default language">
            @foreach ($locales as $code)
                <option value="{{ $code }}">{{ strtoupper($code) }}</option>
            @endforeach
        </flux:select>
        <flux:button type="submit" variant="primary">Save assistant settings</flux:button>
    </form>
@else
    <p class="text-zinc-500">You do not have permission to edit assistant settings.</p>
@endcan
