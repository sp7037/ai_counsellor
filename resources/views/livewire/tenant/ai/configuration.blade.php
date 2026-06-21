<?php

use App\Enums\AI\AiCredentialMode;
use App\Models\AiProvider;
use App\Models\Tenant;
use App\Models\TenantAiConfig;
use App\Services\AI\TenantAiConfigService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.tenant')] class extends Component {
    public Tenant $tenant;

    public string $provider = 'openai';

    public string $model = 'gpt-4o-mini';

    public float $temperature = 0.2;

    public int $maxOutputTokens = 400;

    public int $timeoutSeconds = 15;

    public bool $enabled = true;

    public string $credentialMode = 'platform_managed';

    public string $apiKey = '';

    public bool $replaceSecret = false;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('viewTenantAiConfiguration', $tenant);
        $this->tenant = $tenant;

        $config = TenantAiConfig::query()->with('provider')->first();
        if ($config) {
            $this->provider = $config->provider?->slug ?? 'openai';
            $this->model = $config->model;
            $this->temperature = (float) $config->temperature;
            $this->maxOutputTokens = (int) $config->max_output_tokens;
            $this->timeoutSeconds = (int) $config->timeout_seconds;
            $this->enabled = (bool) $config->enabled;
            $this->credentialMode = $config->credential_mode?->value ?? AiCredentialMode::PlatformManaged->value;
        }
    }

    public function updatedProvider(string $provider): void
    {
        $defaults = [
            'openai' => 'gpt-4o-mini',
            'deepseek' => 'deepseek-v4-flash',
            'fake' => 'fake-model',
        ];

        if (isset($defaults[$provider])) {
            $this->model = $defaults[$provider];
        }
    }

    public function with(): array
    {
        return [
            'providers' => AiProvider::query()->where('enabled', true)->orderBy('name')->get(),
            'config' => TenantAiConfig::query()->with('provider')->first(),
        ];
    }

    public function save(TenantAiConfigService $service): void
    {
        $config = TenantAiConfig::query()->first();
        if ($config) {
            $this->authorize('update', $config);
        } else {
            $this->authorize('viewTenantAiConfiguration', $this->tenant);
        }

        $validated = $this->validate([
            'provider' => ['required', 'string', 'max:40', 'exists:ai_providers,slug'],
            'model' => ['required', 'string', 'max:120'],
            'temperature' => ['required', 'numeric', 'min:'.config('ai.min_temperature', 0.0), 'max:'.config('ai.max_temperature', 1.2)],
            'maxOutputTokens' => ['required', 'integer', 'min:1', 'max:'.config('ai.max_output_tokens_limit', 1200)],
            'timeoutSeconds' => ['required', 'integer', 'min:5', 'max:60'],
            'enabled' => ['required', 'boolean'],
            'credentialMode' => ['required', 'string', 'in:'.implode(',', array_column(AiCredentialMode::cases(), 'value'))],
        ]);

        $payload = [
            'provider' => $validated['provider'],
            'model' => trim($validated['model']),
            'temperature' => (float) $validated['temperature'],
            'max_output_tokens' => (int) $validated['maxOutputTokens'],
            'timeout_seconds' => (int) $validated['timeoutSeconds'],
            'enabled' => (bool) $validated['enabled'],
            'credential_mode' => $validated['credentialMode'],
        ];

        if ($this->replaceSecret) {
            $this->validate([
                'apiKey' => ['nullable', 'string', 'max:255'],
            ]);
            $payload['api_key'] = $this->apiKey;
        }

        $service->upsert($this->tenant, $payload, auth()->user());
        $this->replaceSecret = false;
        $this->apiKey = '';
    }
}; ?>

<x-slot:heading>AI orchestration — {{ $tenant->name }}</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.dashboard', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid gap-6">
    <section class="grid gap-4 rounded-lg border border-zinc-800 bg-zinc-900 p-4">
        <flux:heading size="md">Provider configuration</flux:heading>
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
            <flux:select wire:model="provider" label="Provider">
                @foreach ($providers as $providerOption)
                    <option value="{{ $providerOption->slug }}">{{ $providerOption->name }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="model" label="Model" />
            <flux:input wire:model="temperature" type="number" step="0.01" label="Temperature" />
            <flux:input wire:model="maxOutputTokens" type="number" label="Max output tokens" />
            <flux:input wire:model="timeoutSeconds" type="number" label="Timeout seconds" />
            <flux:checkbox wire:model="enabled" label="Enable AI replies" />

            <flux:select wire:model="credentialMode" label="Credential mode" class="md:col-span-2">
                @foreach (\App\Enums\AI\AiCredentialMode::cases() as $mode)
                    <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                @endforeach
            </flux:select>

            <div class="md:col-span-2 rounded border border-zinc-800 p-3">
                <flux:checkbox wire:model.live="replaceSecret" label="Replace provider key" />
                @if ($replaceSecret)
                    <flux:input wire:model="apiKey" type="password" label="Provider API key (masked after save)" />
                @elseif ($config?->encrypted_api_key)
                    <p class="mt-2 text-xs text-zinc-500">A provider key is stored and masked. Use “Replace provider key” to rotate or clear it.</p>
                @endif
            </div>

            <div class="md:col-span-2">
                <flux:button type="submit" variant="primary">Save configuration</flux:button>
            </div>
        </form>
    </section>
</div>
