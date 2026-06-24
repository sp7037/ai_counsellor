<?php

use App\Services\Billing\PaymentCredentialsService;
use App\Services\Platform\PlatformSettingsService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.platform')] class extends Component {
    public string $default_provider = '';

    public string $default_fallback_message = '';

    public string $support_email = '';

    public bool $widget_powered_by_enabled = true;

    public string $widget_powered_by_label = '';

    public string $widget_powered_by_logo_url = '';

    public string $widget_launcher_logo_url = '';

    public string $widget_launcher_teaser_text = '';

    public string $widget_launcher_card_title = '';

    public string $widget_launcher_card_subtitle = '';

    public string $widget_launcher_card_cta_text = '';

    public string $widget_launcher_card_trust_text = '';

    public int $widget_launcher_card_delay_seconds = 5;

    public int $widget_launcher_card_dismiss_hours = 4;

    public string $widget_launcher_card_animation = 'soft_slide_up';

    public string $platform_api_key = '';

    public string $platform_deepseek_api_key = '';

    public bool $platform_credential_configured = false;

    public bool $openai_credential_configured = false;

    public bool $deepseek_credential_configured = false;

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
        $this->widget_powered_by_enabled = (bool) ($current['widget_powered_by_enabled'] ?? true);
        $this->widget_powered_by_label = (string) ($current['widget_powered_by_label'] ?? 'Powered by SR Worlds AI');
        $this->widget_powered_by_logo_url = (string) ($current['widget_powered_by_logo_url'] ?? '');
        $this->widget_launcher_logo_url = (string) ($current['widget_launcher_logo_url'] ?? '');
        $this->widget_launcher_teaser_text = (string) ($current['widget_launcher_teaser_text'] ?? '');
        $this->widget_launcher_card_title = (string) ($current['widget_launcher_card_title'] ?? '');
        $this->widget_launcher_card_subtitle = (string) ($current['widget_launcher_card_subtitle'] ?? '');
        $this->widget_launcher_card_cta_text = (string) ($current['widget_launcher_card_cta_text'] ?? '');
        $this->widget_launcher_card_trust_text = (string) ($current['widget_launcher_card_trust_text'] ?? '');
        $this->widget_launcher_card_delay_seconds = (int) ($current['widget_launcher_card_delay_seconds'] ?? 5);
        $this->widget_launcher_card_dismiss_hours = (int) ($current['widget_launcher_card_dismiss_hours'] ?? config('widget.launcher_card.dismiss_reshow_seconds', 4));
        $this->widget_launcher_card_animation = (string) ($current['widget_launcher_card_animation'] ?? 'soft_slide_up');
        $this->platform_credential_configured = (bool) $current['platform_credential_configured'];
        $this->openai_credential_configured = (bool) ($current['openai_credential_configured'] ?? false);
        $this->deepseek_credential_configured = (bool) ($current['deepseek_credential_configured'] ?? false);

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
            'default_provider' => ['nullable', 'string', 'max:50', 'in:openai,deepseek,fake'],
            'default_fallback_message' => ['nullable', 'string', 'max:2000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'widget_powered_by_enabled' => ['required', 'boolean'],
            'widget_powered_by_label' => ['nullable', 'string', 'max:120'],
            'widget_powered_by_logo_url' => ['nullable', 'url', 'max:2048'],
            'widget_launcher_logo_url' => ['nullable', 'url', 'max:2048'],
            'widget_launcher_teaser_text' => ['nullable', 'string', 'max:120'],
            'widget_launcher_card_title' => ['nullable', 'string', 'max:120'],
            'widget_launcher_card_subtitle' => ['nullable', 'string', 'max:280'],
            'widget_launcher_card_cta_text' => ['nullable', 'string', 'max:60'],
            'widget_launcher_card_trust_text' => ['nullable', 'string', 'max:80'],
            'widget_launcher_card_delay_seconds' => ['required', 'integer', 'min:0', 'max:30'],
            'widget_launcher_card_dismiss_hours' => ['required', 'integer', 'min:3', 'max:10'],
            'widget_launcher_card_animation' => ['required', 'string', 'in:none,soft_slide_up,gentle_pulse,soft_bounce_once'],
            'platform_api_key' => ['nullable', 'string', 'max:500'],
            'platform_deepseek_api_key' => ['nullable', 'string', 'max:500'],
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
            'widget_powered_by_enabled' => $this->widget_powered_by_enabled,
            'widget_powered_by_label' => $this->widget_powered_by_label ?: null,
            'widget_powered_by_logo_url' => $this->widget_powered_by_logo_url ?: null,
            'widget_launcher_logo_url' => $this->widget_launcher_logo_url ?: null,
            'widget_launcher_teaser_text' => $this->widget_launcher_teaser_text ?: null,
            'widget_launcher_card_title' => $this->widget_launcher_card_title ?: null,
            'widget_launcher_card_subtitle' => $this->widget_launcher_card_subtitle ?: null,
            'widget_launcher_card_cta_text' => $this->widget_launcher_card_cta_text ?: null,
            'widget_launcher_card_trust_text' => $this->widget_launcher_card_trust_text ?: null,
            'widget_launcher_card_delay_seconds' => $this->widget_launcher_card_delay_seconds,
            'widget_launcher_card_dismiss_hours' => $this->widget_launcher_card_dismiss_hours,
            'widget_launcher_card_animation' => $this->widget_launcher_card_animation,
            'platform_api_key' => $this->platform_api_key,
            'platform_deepseek_api_key' => $this->platform_deepseek_api_key,
        ], auth()->user());

        $payments->updateSettings([
            'payment_enabled' => $this->payment_enabled,
            'payment_environment' => $this->payment_environment,
            'payment_provider' => $this->payment_provider,
            'payment_key_id' => $this->payment_key_id,
            'payment_key_secret' => $this->payment_key_secret,
            'payment_webhook_secret' => $this->payment_webhook_secret,
        ], auth()->user(), app(\App\Services\Audit\AuditLogger::class));

        $this->reset('platform_api_key', 'platform_deepseek_api_key', 'payment_key_secret', 'payment_webhook_secret');
        $this->platform_credential_configured = $settings->platformCredentialConfigured();
        $this->openai_credential_configured = $settings->platformCredentialConfigured('openai');
        $this->deepseek_credential_configured = $settings->platformCredentialConfigured('deepseek');
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
        <flux:select wire:model="default_provider" label="Default AI provider">
            <option value="">Use environment default</option>
            <option value="openai">OpenAI</option>
            <option value="deepseek">DeepSeek</option>
            <option value="fake">Fake (tests)</option>
        </flux:select>
        <flux:textarea wire:model="default_fallback_message" label="Default safe fallback message" rows="3" />
        <flux:input wire:model="support_email" label="Support email" type="email" />

        <div class="rounded border border-zinc-800 p-4 text-sm">
            <flux:heading size="sm">Widget powered by</flux:heading>
            <flux:checkbox wire:model="widget_powered_by_enabled" class="mt-2" label="Show powered-by chip in widget footer" />
            <flux:input wire:model="widget_powered_by_label" class="mt-3" label="Powered-by label" placeholder="Powered by SR Worlds AI" />
            <flux:input wire:model="widget_powered_by_logo_url" class="mt-3" label="Powered-by logo URL (optional)" placeholder="https://example.com/logo.png" />
            <flux:input wire:model="widget_launcher_logo_url" class="mt-3" label="Launcher logo URL (circle button fallback)" placeholder="https://example.com/launcher-logo.png" />
            <flux:text class="mt-1 text-xs text-zinc-500">Used for the circle launcher and as a card image fallback when a tenant has no card image.</flux:text>
            <flux:input wire:model="widget_launcher_teaser_text" class="mt-3" label="Circle launcher teaser text" placeholder="Ask AI Counsellor" />
        </div>

        <div class="rounded border border-zinc-800 p-4 text-sm">
            <flux:heading size="sm">Card launcher defaults</flux:heading>
            <flux:text class="mt-1 text-xs text-zinc-500">Tenants can override these in Configuration → Widget launcher. Empty tenant fields inherit these defaults.</flux:text>
            <flux:input wire:model="widget_launcher_card_title" class="mt-3" label="Default card title" />
            <flux:textarea wire:model="widget_launcher_card_subtitle" class="mt-3" label="Default card subtitle" rows="2" />
            <flux:input wire:model="widget_launcher_card_cta_text" class="mt-3" label="Default CTA text" />
            <flux:input wire:model="widget_launcher_card_trust_text" class="mt-3" label="Default trust line" />
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <flux:input wire:model="widget_launcher_card_delay_seconds" type="number" min="0" max="30" label="Default auto-show delay (seconds)" />
                <flux:input wire:model="widget_launcher_card_dismiss_hours" type="number" min="3" max="10" label="Default hide after close (seconds)" />
            </div>
            <flux:select wire:model="widget_launcher_card_animation" class="mt-3" label="Default animation">
                <option value="none">None</option>
                <option value="soft_slide_up">Soft slide-up</option>
                <option value="gentle_pulse">Gentle pulse</option>
                <option value="soft_bounce_once">Soft bounce once</option>
            </flux:select>

        <div class="rounded border border-zinc-800 p-4 text-sm">
            <p class="text-zinc-400">Platform OpenAI credential: {{ $openai_credential_configured ? 'Configured (value not shown)' : 'Not configured' }}</p>
            <flux:input wire:model="platform_api_key" class="mt-3" label="Replace OpenAI API key" type="password" placeholder="Leave blank to keep unchanged" />
        </div>

        <div class="rounded border border-zinc-800 p-4 text-sm">
            <p class="text-zinc-400">Platform DeepSeek credential: {{ $deepseek_credential_configured ? 'Configured (value not shown)' : 'Not configured' }}</p>
            <flux:input wire:model="platform_deepseek_api_key" class="mt-3" label="Replace DeepSeek API key" type="password" placeholder="Leave blank to keep unchanged" />
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
