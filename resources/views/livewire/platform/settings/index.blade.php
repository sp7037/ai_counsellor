<?php

use App\Services\Billing\PaymentCredentialsService;
use App\Services\Platform\PlatformSettingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public string $default_provider = '';

    public string $default_fallback_message = '';

    public string $support_email = '';

    public string $platform_api_key = '';

    public bool $platform_credential_configured = false;

    public bool $payment_enabled = true;

    public string $payment_environment = 'test';

    public string $payment_provider = 'fake';

    public string $payment_key_id = '';

    public string $payment_key_secret = '';

    public string $payment_webhook_secret = '';

    public bool $payment_key_secret_configured = false;

    public bool $payment_webhook_secret_configured = false;

    public function mount(PlatformSettingsService $settings, PaymentCredentialsService $payments): void
    {
        $current = $settings->all();
        $this->default_provider = (string) ($current['default_provider'] ?? '');
        $this->default_fallback_message = (string) ($current['default_fallback_message'] ?? '');
        $this->support_email = (string) ($current['support_email'] ?? '');
        $this->platform_credential_configured = (bool) $current['platform_credential_configured'];

        $payment = $payments->safeSummary();
        $this->payment_enabled = (bool) $payment['payment_enabled'];
        $this->payment_environment = (string) $payment['payment_environment'];
        $this->payment_provider = (string) $payment['payment_provider'];
        $this->payment_key_secret_configured = (bool) $payment['payment_key_secret_configured'];
        $this->payment_webhook_secret_configured = (bool) $payment['payment_webhook_secret_configured'];
    }

    public function save(PlatformSettingsService $settings, PaymentCredentialsService $payments): void
    {
        $this->validate([
            'default_provider' => ['nullable', 'string', 'max:50'],
            'default_fallback_message' => ['nullable', 'string', 'max:2000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'platform_api_key' => ['nullable', 'string', 'max:500'],
            'payment_environment' => ['required', 'in:test,live'],
            'payment_provider' => ['required', 'in:fake,razorpay'],
            'payment_key_id' => ['nullable', 'string', 'max:255'],
            'payment_key_secret' => ['nullable', 'string', 'max:500'],
            'payment_webhook_secret' => ['nullable', 'string', 'max:500'],
        ]);

        $settings->update([
            'default_provider' => $this->default_provider ?: null,
            'default_fallback_message' => $this->default_fallback_message ?: null,
            'support_email' => $this->support_email ?: null,
            'platform_api_key' => $this->platform_api_key,
        ], auth()->user());

        $payments->updateSettings([
            'payment_enabled' => $this->payment_enabled,
            'payment_environment' => $this->payment_environment,
            'payment_provider' => $this->payment_provider,
            'payment_key_id' => $this->payment_key_id,
            'payment_key_secret' => $this->payment_key_secret,
            'payment_webhook_secret' => $this->payment_webhook_secret,
        ], auth()->user(), app(\App\Services\Audit\AuditLogger::class));

        $this->reset('platform_api_key', 'payment_key_secret', 'payment_webhook_secret');
        $this->platform_credential_configured = $settings->platformCredentialConfigured();
        $summary = $payments->safeSummary();
        $this->payment_key_secret_configured = (bool) $summary['payment_key_secret_configured'];
        $this->payment_webhook_secret_configured = (bool) $summary['payment_webhook_secret_configured'];

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

    <form wire:submit="save" class="mt-6 grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-6">
        <flux:heading size="md">Payment provider</flux:heading>
        <flux:checkbox wire:model="payment_enabled" label="Payments enabled" />
        <flux:select wire:model="payment_environment" label="Environment">
            <option value="test">Test</option>
            <option value="live">Live</option>
        </flux:select>
        <flux:select wire:model="payment_provider" label="Provider">
            <option value="fake">Fake (local/testing)</option>
            <option value="razorpay">Razorpay</option>
        </flux:select>
        <flux:input wire:model="payment_key_id" label="Key ID (public)" placeholder="rzp_test_..." />
        <div class="rounded border border-zinc-800 p-4 text-sm">
            <p class="text-zinc-400">Key secret: {{ $payment_key_secret_configured ? 'Configured (not shown)' : 'Not configured' }}</p>
            <flux:input wire:model="payment_key_secret" class="mt-3" label="Replace key secret" type="password" placeholder="Leave blank to keep unchanged" />
        </div>
        <div class="rounded border border-zinc-800 p-4 text-sm">
            <p class="text-zinc-400">Webhook secret: {{ $payment_webhook_secret_configured ? 'Configured (not shown)' : 'Not configured' }}</p>
            <flux:input wire:model="payment_webhook_secret" class="mt-3" label="Replace webhook secret" type="password" placeholder="Leave blank to keep unchanged" />
        </div>
        <flux:text class="text-xs text-zinc-500">Webhook URL: {{ url('/webhooks/payments/razorpay') }} (or /fake for test adapter)</flux:text>
        <flux:button type="submit" variant="primary">Save payment settings</flux:button>
    </form>
</div>
