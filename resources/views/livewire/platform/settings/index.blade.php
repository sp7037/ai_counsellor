<?php

use App\Services\Platform\PlatformSettingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public string $default_provider = '';

    public string $default_fallback_message = '';

    public string $support_email = '';

    public string $platform_api_key = '';

    public bool $platform_credential_configured = false;

    public function mount(PlatformSettingsService $settings): void
    {
        $current = $settings->all();
        $this->default_provider = (string) ($current['default_provider'] ?? '');
        $this->default_fallback_message = (string) ($current['default_fallback_message'] ?? '');
        $this->support_email = (string) ($current['support_email'] ?? '');
        $this->platform_credential_configured = (bool) $current['platform_credential_configured'];
    }

    public function save(PlatformSettingsService $settings): void
    {
        $this->validate([
            'default_provider' => ['nullable', 'string', 'max:50'],
            'default_fallback_message' => ['nullable', 'string', 'max:2000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'platform_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $settings->update([
            'default_provider' => $this->default_provider ?: null,
            'default_fallback_message' => $this->default_fallback_message ?: null,
            'support_email' => $this->support_email ?: null,
            'platform_api_key' => $this->platform_api_key,
        ], auth()->user());

        $this->reset('platform_api_key');
        $this->platform_credential_configured = $settings->platformCredentialConfigured();

        session()->flash('status', 'Platform settings saved.');
    }
}; ?>

<x-slot:heading>Platform settings</x-slot:heading>

<div class="max-w-2xl">
    @if (session('status'))
        <div class="mb-4 rounded border border-green-900/50 bg-green-950/30 px-4 py-3 text-sm text-green-300">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
        <flux:input wire:model="default_provider" label="Default AI provider" />
        <flux:textarea wire:model="default_fallback_message" label="Default safe fallback message" rows="3" />
        <flux:input wire:model="support_email" label="Support email" type="email" />

        <div class="rounded border border-zinc-800 p-4 text-sm">
            <p class="text-zinc-400">Platform OpenAI credential: {{ $platform_credential_configured ? 'Configured (value not shown)' : 'Not configured' }}</p>
            <flux:input wire:model="platform_api_key" class="mt-3" label="Replace platform API key" type="password" placeholder="Leave blank to keep unchanged" />
        </div>

        <flux:button type="submit" variant="primary">Save settings</flux:button>
    </form>
</div>
