<?php

use App\Enums\Configuration\LauncherAnimation;
use App\Enums\Configuration\LauncherMode;
use App\Models\Tenant;
use App\Services\Configuration\TenantConfigurationResolver;
use App\Services\Configuration\TenantLauncherConfigurationService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.tenant')] class extends Component {
    use WithFileUploads;

    public Tenant $tenant;

    public string $launcherMode = 'circle';

    public string $cardTitle = '';

    public string $cardSubtitle = '';

    public string $cardCtaText = '';

    public string $cardTrustText = '';

    public ?int $cardDelaySeconds = null;

    public ?int $cardDismissHours = null;

    public string $cardAnimation = '';

    public $cardImageUpload = null;

    public function mount(Tenant $tenant, TenantConfigurationResolver $resolver): void
    {
        $this->authorize('viewTenantConfiguration', $tenant);
        $this->tenant = $tenant;

        $widgetSettings = $resolver->widgetSettings($tenant);

        $this->launcherMode = $widgetSettings->launcher_mode?->value ?? LauncherMode::Circle->value;
        $this->cardTitle = (string) ($widgetSettings->launcher_card_title ?? '');
        $this->cardSubtitle = (string) ($widgetSettings->launcher_card_subtitle ?? '');
        $this->cardCtaText = (string) ($widgetSettings->launcher_card_cta_text ?? '');
        $this->cardTrustText = (string) ($widgetSettings->launcher_card_trust_text ?? '');
        $this->cardDelaySeconds = $widgetSettings->launcher_card_delay_seconds;
        $this->cardDismissHours = $widgetSettings->launcher_card_dismiss_hours;
        $this->cardAnimation = (string) ($widgetSettings->launcher_card_animation?->value ?? '');
    }

    public function with(TenantConfigurationResolver $resolver): array
    {
        $widgetSettings = $resolver->widgetSettings($this->tenant);

        return [
            'cardImageUrl' => $widgetSettings->launcher_card_image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($widgetSettings->launcher_card_image_path)
                : null,
            'launcherModes' => LauncherMode::cases(),
            'animations' => LauncherAnimation::cases(),
            'platformDefaults' => config('widget.launcher_card', []),
        ];
    }

    public function save(TenantLauncherConfigurationService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);

        $this->validate([
            'launcherMode' => ['required', 'in:'.implode(',', array_column(LauncherMode::cases(), 'value'))],
            'cardTitle' => ['nullable', 'string', 'max:'.config('configuration.max_launcher_card_title_length', 120)],
            'cardSubtitle' => ['nullable', 'string', 'max:'.config('configuration.max_launcher_card_subtitle_length', 280)],
            'cardCtaText' => ['nullable', 'string', 'max:'.config('configuration.max_launcher_card_cta_length', 60)],
            'cardTrustText' => ['nullable', 'string', 'max:'.config('configuration.max_launcher_card_trust_length', 80)],
            'cardDelaySeconds' => ['nullable', 'integer', 'min:0', 'max:30'],
            'cardDismissHours' => ['nullable', 'integer', 'min:3', 'max:10'],
            'cardAnimation' => ['nullable', 'string', 'in:'.implode(',', array_column(LauncherAnimation::cases(), 'value'))],
        ]);

        $service->update($this->tenant, [
            'launcher_mode' => $this->launcherMode,
            'launcher_card_title' => $this->cardTitle !== '' ? $this->cardTitle : null,
            'launcher_card_subtitle' => $this->cardSubtitle !== '' ? $this->cardSubtitle : null,
            'launcher_card_cta_text' => $this->cardCtaText !== '' ? $this->cardCtaText : null,
            'launcher_card_trust_text' => $this->cardTrustText !== '' ? $this->cardTrustText : null,
            'launcher_card_delay_seconds' => $this->cardDelaySeconds,
            'launcher_card_dismiss_hours' => $this->cardDismissHours,
            'launcher_card_animation' => $this->cardAnimation !== '' ? $this->cardAnimation : null,
        ], auth()->user());

        session()->flash('status', 'Widget launcher settings saved.');
    }

    public function uploadCardImage(TenantLauncherConfigurationService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);

        $this->validate([
            'cardImageUpload' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $service->uploadCardImage($this->tenant, $this->cardImageUpload, auth()->user());
        $this->reset('cardImageUpload');
        session()->flash('status', 'Launcher card image uploaded.');
    }

    public function removeCardImage(TenantLauncherConfigurationService $service): void
    {
        Gate::authorize('manageTenantConfiguration', $this->tenant);
        $service->removeCardImage($this->tenant, auth()->user());
        session()->flash('status', 'Launcher card image removed.');
    }
}; ?>

<x-slot:heading>Widget launcher</x-slot:heading>
<x-slot:actions>
    <flux:button href="{{ route('tenant.configuration.index', $tenant) }}" wire:navigate variant="ghost" size="sm">Back</flux:button>
</x-slot:actions>

<div class="grid max-w-2xl gap-6">
    @if (session('status'))
        <div class="rounded border border-green-900/50 bg-green-950/30 px-4 py-3 text-sm text-green-300">{{ session('status') }}</div>
    @endif

    <p class="text-sm text-zinc-400">
        Configure how the chat invitation appears on your website. Leave text fields blank to use platform defaults.
    </p>

    @if ($cardImageUrl)
        <div class="rounded border border-zinc-800 p-4">
            <p class="mb-2 text-xs text-zinc-500">Current card image preview</p>
            <img src="{{ $cardImageUrl }}" alt="Launcher card image" class="max-h-40 rounded-lg object-cover">
            @can('manageTenantConfiguration', $tenant)
                <flux:button wire:click="removeCardImage" class="mt-3" variant="danger" size="sm">Remove card image</flux:button>
            @endcan
        </div>
    @endif

    @can('manageTenantConfiguration', $tenant)
        <form wire:submit="uploadCardImage" class="grid gap-3 rounded border border-zinc-800 p-4">
            <flux:input type="file" wire:model="cardImageUpload" label="Card counsellor image (1024×1024 recommended, JPEG/PNG/WebP, max 2 MB)" />
            <flux:text class="text-xs text-zinc-500">Portrait only — do not bake text into the image. Falls back to your organisation logo, then platform default.</flux:text>
            <flux:button type="submit" variant="ghost" size="sm">Upload card image</flux:button>
        </form>

        <form wire:submit="save" class="grid gap-4 rounded border border-zinc-800 bg-zinc-900 p-4">
            <flux:select wire:model="launcherMode" label="Launcher mode">
                @foreach ($launcherModes as $mode)
                    <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="cardTitle" label="Card title" placeholder="{{ $platformDefaults['title'] ?? 'Need help?' }}" />
            <flux:textarea wire:model="cardSubtitle" label="Card subtitle" rows="3" placeholder="{{ $platformDefaults['subtitle'] ?? '' }}" />
            <flux:input wire:model="cardCtaText" label="CTA button text" placeholder="{{ $platformDefaults['cta_text'] ?? 'Start chat' }}" />
            <flux:input wire:model="cardTrustText" label="Trust line (optional)" placeholder="{{ $platformDefaults['trust_text'] ?? '' }}" />

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="cardDelaySeconds" type="number" min="0" max="30" label="Auto-show delay (seconds)" placeholder="{{ $platformDefaults['delay_seconds'] ?? 5 }}" />
                <flux:input wire:model="cardDismissHours" type="number" min="3" max="10" label="Hide after close (seconds)" placeholder="{{ $platformDefaults['dismiss_reshow_seconds'] ?? 4 }}" />
                <flux:text class="text-xs text-zinc-500">When a visitor taps X, the card hides briefly then returns automatically.</flux:text>
            </div>

            <flux:select wire:model="cardAnimation" label="Animation">
                <option value="">Use platform default ({{ str_replace('_', ' ', $platformDefaults['animation'] ?? 'soft slide up') }})</option>
                @foreach ($animations as $animation)
                    <option value="{{ $animation->value }}">{{ $animation->label() }}</option>
                @endforeach
            </flux:select>

            <flux:button type="submit" variant="primary">Save launcher settings</flux:button>
        </form>
    @else
        <p class="text-zinc-500">You can view launcher settings but cannot change them.</p>
    @endcan
</div>
