<?php

use App\Enums\Messaging\MessagingProvider;
use App\Models\Tenant;
use App\Models\TenantMessagingIntegration;
use App\Services\Messaging\MessagingIntegrationService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $phone_number_id = '';

    public string $waba_id = '';

    public string $display_phone_number = '';

    public string $business_display_name = '';

    public string $verify_token = '';

    public string $access_token = '';

    public string $app_secret = '';

    public bool $confirm_disconnect = false;

    public function mount(Tenant $tenant, MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($tenant);
        $this->authorize('view', $integration);
        $this->tenant = $tenant;

        $this->phone_number_id = (string) ($integration->phone_number_id ?? '');
        $this->waba_id = (string) ($integration->waba_id ?? '');
        $this->display_phone_number = (string) ($integration->display_phone_number ?? '');
        $this->business_display_name = (string) ($integration->business_display_name ?? '');
    }

    public function with(MessagingIntegrationService $integrations): array
    {
        $integration = $integrations->forTenant($this->tenant);

        return [
            'integration' => $integration,
            'summary' => $integrations->safeSummary($this->tenant),
            'webhookUrl' => route('webhooks.messaging', ['provider' => $integration->provider === MessagingProvider::Fake ? 'fake' : 'meta']),
            'verifyToken' => $integration->verify_token,
        ];
    }

    public function save(MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($this->tenant);
        $this->authorize('configure', $integration);

        $validated = $this->validate([
            'phone_number_id' => ['required', 'string', 'max:64'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'display_phone_number' => ['nullable', 'string', 'max:32'],
            'business_display_name' => ['nullable', 'string', 'max:120'],
            'verify_token' => ['nullable', 'string', 'max:128'],
            'access_token' => ['nullable', 'string', 'max:2048'],
            'app_secret' => ['nullable', 'string', 'max:512'],
        ]);

        $integrations->configure($this->tenant, $validated, auth()->user());

        $this->reset('access_token', 'app_secret');
        session()->flash('status', 'WhatsApp integration saved.');
    }

    public function enable(MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($this->tenant);
        $this->authorize('enable', $integration);
        $integrations->enable($this->tenant, auth()->user());
        session()->flash('status', 'WhatsApp integration enabled.');
    }

    public function disable(MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($this->tenant);
        $this->authorize('disable', $integration);
        $integrations->disable($this->tenant, auth()->user());
        session()->flash('status', 'WhatsApp integration disabled.');
    }

    public function disconnect(MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($this->tenant);
        $this->authorize('disable', $integration);
        $integrations->disconnect($this->tenant, auth()->user());
        $this->confirm_disconnect = false;
        session()->flash('status', 'WhatsApp integration disconnected. Historic data is preserved.');
    }

    public function testConnection(MessagingIntegrationService $integrations): void
    {
        $integration = $integrations->forTenant($this->tenant);
        $this->authorize('configure', $integration);
        $integrations->testConnection($this->tenant, auth()->user());
        session()->flash('status', 'Configuration check passed.');
    }
}; ?>

<x-slot:heading>WhatsApp integration</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.integrations.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Integrations</flux:button>
    <flux:button href="{{ route('tenant.integrations.whatsapp.templates', $tenant) }}" wire:navigate variant="ghost" size="sm">Templates</flux:button>
    <flux:button href="{{ route('tenant.integrations.whatsapp.events', $tenant) }}" wire:navigate variant="ghost" size="sm">Events</flux:button>
</x-slot:actions>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 grid gap-6">
        @if (session('status'))
            <flux:callout variant="success">{{ session('status') }}</flux:callout>
        @endif

        <form wire:submit="save" class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-4">
            <flux:heading size="md">Connection settings</flux:heading>
            <flux:input wire:model="phone_number_id" label="Phone number ID" required />
            <flux:input wire:model="waba_id" label="WhatsApp Business Account ID" />
            <flux:input wire:model="display_phone_number" label="Display phone number" />
            <flux:input wire:model="business_display_name" label="Business display name" />
            <flux:input wire:model="verify_token" label="Verify token (leave blank to keep current)" />
            <flux:input wire:model="access_token" type="password" label="Access token (leave blank to keep current)" autocomplete="off" />
            <flux:input wire:model="app_secret" type="password" label="App secret (leave blank to keep current)" autocomplete="off" />
            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary">Save configuration</flux:button>
                <flux:button type="button" wire:click="testConnection" variant="ghost">Test configuration</flux:button>
            </div>
        </form>

        <div class="flex flex-wrap gap-2">
            @if ($integration->is_enabled)
                <flux:button wire:click="disable" variant="ghost" size="sm">Disable integration</flux:button>
            @else
                <flux:button wire:click="enable" variant="primary" size="sm">Enable integration</flux:button>
            @endif
            <flux:button wire:click="$set('confirm_disconnect', true)" variant="danger" size="sm">Disconnect</flux:button>
        </div>

        @if ($confirm_disconnect)
            <flux:card class="grid gap-3 border-red-900/50 p-4">
                <flux:heading size="sm">Confirm disconnect</flux:heading>
                <p class="text-sm text-zinc-400">This stops new outbound messages. Conversations, messages and leads are preserved.</p>
                <div class="flex gap-2">
                    <flux:button wire:click="disconnect" variant="danger" size="sm">Confirm disconnect</flux:button>
                    <flux:button wire:click="$set('confirm_disconnect', false)" variant="ghost" size="sm">Cancel</flux:button>
                </div>
            </flux:card>
        @endif
    </div>

    <aside class="grid gap-4">
        <flux:card class="grid gap-3 p-4 text-sm">
            <flux:heading size="sm">Connection status</flux:heading>
            <p>Status: <span class="text-white">{{ $summary['status'] }}</span></p>
            <p>Enabled: <span class="text-white">{{ $summary['is_enabled'] ? 'Yes' : 'No' }}</span></p>
            <p>Access token: <span class="text-white">{{ $summary['access_token_configured'] ? 'Configured' : 'Not configured' }}</span></p>
            <p>App secret: <span class="text-white">{{ $summary['app_secret_configured'] ? 'Configured' : 'Not configured' }}</span></p>
            @if ($summary['last_webhook_at'])
                <p class="text-zinc-400">Last webhook: {{ $summary['last_webhook_at'] }}</p>
            @endif
        </flux:card>

        <flux:card class="grid gap-3 p-4 text-sm">
            <flux:heading size="sm">Webhook setup</flux:heading>
            <p class="text-zinc-400">Register this callback URL in your Meta developer app:</p>
            <code class="break-all rounded bg-zinc-950 p-2 text-xs">{{ $webhookUrl }}</code>
            <p class="text-zinc-400">Verify token:</p>
            <code class="break-all rounded bg-zinc-950 p-2 text-xs">{{ $verifyToken }}</code>
            <p class="text-zinc-400">Subscribe to <code>messages</code> webhook fields. Use your app secret for signature verification.</p>
        </flux:card>
    </aside>
</div>
