<?php

namespace App\Services\Configuration;

use App\Enums\Configuration\LauncherAnimation;
use App\Enums\Configuration\LauncherMode;
use App\Enums\Configuration\CatalogueStatus;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Branding;
use Illuminate\Support\Facades\Storage;

class WidgetPublicConfigService
{
    public function __construct(
        private readonly TenantConfigurationResolver $resolver,
        private readonly OfficeHoursEvaluator $officeHoursEvaluator,
    ) {}

    public function forTenant(Tenant $tenant): array
    {
        $settings = $this->resolver->settings($tenant);
        $widgetSettings = $this->resolver->widgetSettings($tenant);
        $availability = $this->officeHoursEvaluator->evaluate($tenant);
        $platformSettings = PlatformSetting::query()->pluck('value', 'key');
        $chrome = $this->platformChrome($tenant, $settings, $widgetSettings, $platformSettings);

        return [
            'branding' => $chrome['branding'],
            'locale' => [
                'default' => $settings->default_locale,
                'supported' => $settings->supported_locales ?? [$settings->default_locale],
            ],
            'messages' => [
                'welcome' => $widgetSettings->welcome_message,
                'offline' => $widgetSettings->offline_message,
                'offline_form_enabled' => $widgetSettings->offline_form_enabled,
                'welcome_delay_seconds' => $widgetSettings->welcome_delay_seconds,
            ],
            'ai_disclosure' => [
                'enabled' => $settings->ai_disclosure_enabled,
                'message' => $settings->ai_disclosure_message,
            ],
            'human_transfer' => [
                'enabled' => $settings->human_transfer_enabled,
                'label' => $settings->human_transfer_label ?: 'Talk to counsellor',
                'subtle_label' => (string) config('widget.handoff.subtle_label', 'Need human help?'),
                'message' => $settings->human_transfer_message,
                'promote_after_messages' => (int) config('widget.handoff.promote_after_messages', 3),
                'offer_message' => (string) config('widget.handoff.offer_message', 'You can continue here or request a human counsellor.'),
            ],
            'powered_by' => $chrome['powered_by'],
            'launcher' => $chrome['launcher'],
            'availability' => $availability,
            'catalogue' => [
                'services' => $this->mapCatalogue(Service::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
                'courses' => $this->mapCourses(Course::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
                'institutions' => $this->mapInstitutions(Institution::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
                'locations' => $this->mapLocations(Location::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
            ],
        ];
    }

    /**
     * Lightweight, session-less public chrome (branding + launcher + powered-by) used to render
     * the floating launcher immediately on first page load without creating a conversation.
     *
     * @return array<string, mixed>
     */
    public function chromeFor(Tenant $tenant): array
    {
        $settings = $this->resolver->settings($tenant);
        $widgetSettings = $this->resolver->widgetSettings($tenant);
        $platformSettings = PlatformSetting::query()->pluck('value', 'key');

        return $this->platformChrome($tenant, $settings, $widgetSettings, $platformSettings);
    }

    /**
     * @return array{branding: array<string, mixed>, powered_by: array<string, mixed>, launcher: array<string, mixed>}
     */
    private function platformChrome(Tenant $tenant, $settings, $widgetSettings, $platformSettings): array
    {
        $poweredByEnabled = (bool) ($platformSettings['widget_powered_by_enabled'] ?? config('widget.powered_by.enabled', true));
        $poweredByLabel = (string) ($platformSettings['widget_powered_by_label'] ?? config('widget.powered_by.label', 'Powered by SR Worlds AI'));
        $poweredByLogoUrl = (string) ($platformSettings['widget_powered_by_logo_url'] ?? '');

        $launcherLogoUrl = trim((string) ($platformSettings['widget_launcher_logo_url'] ?? config('widget.launcher.logo_url', '')));
        $launcherFromPlatform = $launcherLogoUrl !== '';
        $tenantHeaderLogoUrl = $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null;
        $cardImageUrl = $this->resolveLauncherCardImageUrl(
            $widgetSettings,
            $tenantHeaderLogoUrl,
            $launcherFromPlatform ? $launcherLogoUrl : asset(Branding::logoPath()),
        );

        $cardDefaults = (array) config('widget.launcher_card', []);
        $card = [
            'image_url' => $cardImageUrl,
            'title' => $this->resolveLauncherCardField(
                $widgetSettings->launcher_card_title,
                $platformSettings['widget_launcher_card_title'] ?? null,
                (string) ($cardDefaults['title'] ?? 'Need help?'),
            ),
            'subtitle' => $this->resolveLauncherCardField(
                $widgetSettings->launcher_card_subtitle,
                $platformSettings['widget_launcher_card_subtitle'] ?? null,
                (string) ($cardDefaults['subtitle'] ?? 'Ask our AI counsellor anything.'),
            ),
            'cta_text' => $this->resolveLauncherCardField(
                $widgetSettings->launcher_card_cta_text,
                $platformSettings['widget_launcher_card_cta_text'] ?? null,
                (string) ($cardDefaults['cta_text'] ?? 'Start chat'),
            ),
            'trust_text' => $this->resolveLauncherCardField(
                $widgetSettings->launcher_card_trust_text,
                $platformSettings['widget_launcher_card_trust_text'] ?? null,
                (string) ($cardDefaults['trust_text'] ?? ''),
            ),
            'delay_seconds' => $widgetSettings->launcher_card_delay_seconds
                ?? ($platformSettings['widget_launcher_card_delay_seconds'] ?? null)
                ?? (int) ($cardDefaults['delay_seconds'] ?? 5),
            'dismiss_reshow_seconds' => $this->resolveCardSnoozeSeconds($widgetSettings, $platformSettings, $cardDefaults),
            'dismiss_hours' => $widgetSettings->launcher_card_dismiss_hours
                ?? ($platformSettings['widget_launcher_card_dismiss_hours'] ?? null)
                ?? (int) ($cardDefaults['dismiss_hours'] ?? 24),
            'animation' => $this->resolveLauncherCardAnimation($widgetSettings, $platformSettings, $cardDefaults),
        ];

        return [
            'branding' => [
                'display_name' => $settings->display_name ?: $tenant->name,
                'assistant_name' => $settings->assistant_name ?: ($settings->display_name ?: 'AI Counsellor'),
                'assistant_title' => $settings->assistant_title,
                'primary_color' => $settings->primary_color,
                'accent_color' => $settings->accent_color,
                'logo_url' => $tenantHeaderLogoUrl,
                'widget_position' => $widgetSettings->widget_position?->value ?? 'bottom_right',
            ],
            'powered_by' => [
                'enabled' => $poweredByEnabled,
                'label' => $poweredByLabel,
                'logo_url' => $poweredByLogoUrl !== '' ? $poweredByLogoUrl : asset(Branding::logoPath()),
            ],
            'launcher' => [
                'mode' => $widgetSettings->launcher_mode?->value ?? LauncherMode::Circle->value,
                'logo_url' => $launcherFromPlatform ? $launcherLogoUrl : asset(Branding::logoPath()),
                'source' => 'platform',
                'teaser_text' => (string) ($platformSettings['widget_launcher_teaser_text'] ?? config('widget.launcher.teaser_text', 'Ask AI Counsellor')),
                'card' => $card,
            ],
        ];
    }

    private function resolveLauncherCardField(?string $tenantValue, mixed $platformValue, string $fallback): string
    {
        if ($tenantValue !== null && trim($tenantValue) !== '') {
            return trim($tenantValue);
        }

        if (is_string($platformValue) && trim($platformValue) !== '') {
            return trim($platformValue);
        }

        return $fallback;
    }

    private function resolveLauncherCardAnimation($widgetSettings, $platformSettings, array $defaults): string
    {
        $tenantAnimation = $widgetSettings->launcher_card_animation?->value;
        if ($tenantAnimation !== null) {
            return $tenantAnimation;
        }

        $platformAnimation = $platformSettings['widget_launcher_card_animation'] ?? null;
        if (is_string($platformAnimation) && $platformAnimation !== '') {
            return $platformAnimation;
        }

        return (string) ($defaults['animation'] ?? LauncherAnimation::SoftSlideUp->value);
    }

    private function resolveLauncherCardImageUrl($widgetSettings, ?string $tenantHeaderLogoUrl, string $platformLauncherLogoUrl): ?string
    {
        if ($widgetSettings->launcher_card_image_path) {
            return Storage::disk('public')->url($widgetSettings->launcher_card_image_path);
        }

        if ($tenantHeaderLogoUrl) {
            return $tenantHeaderLogoUrl;
        }

        return $platformLauncherLogoUrl !== '' ? $platformLauncherLogoUrl : null;
    }

    private function resolveCardSnoozeSeconds($widgetSettings, $platformSettings, array $defaults): int
    {
        foreach ([
            $widgetSettings->launcher_card_dismiss_hours,
            $platformSettings['widget_launcher_card_dismiss_hours'] ?? null,
        ] as $value) {
            if ($value === null) {
                continue;
            }

            $seconds = (int) $value;
            if ($seconds >= 3 && $seconds <= 10) {
                return $seconds;
            }
        }

        return max(3, min(10, (int) ($defaults['dismiss_reshow_seconds'] ?? 4)));
    }

    private function mapCatalogue($items): array
    {
        return $items->map(fn ($item) => [
            'uuid' => $item->uuid,
            'name' => $item->name,
            'description' => $item->description,
        ])->all();
    }

    private function mapCourses($items): array
    {
        return $items->map(fn (Course $course) => [
            'uuid' => $course->uuid,
            'name' => $course->name,
            'description' => $course->description,
            'duration' => $course->duration,
            'study_mode' => $course->study_mode?->value,
        ])->all();
    }

    private function mapInstitutions($items): array
    {
        return $items->map(fn (Institution $institution) => [
            'uuid' => $institution->uuid,
            'name' => $institution->name,
            'description' => $institution->description,
            'city' => $institution->city,
            'state' => $institution->state,
            'country' => $institution->country,
        ])->all();
    }

    private function mapLocations($items): array
    {
        return $items->map(fn (Location $location) => [
            'uuid' => $location->uuid,
            'name' => $location->name,
            'city' => $location->city,
            'state' => $location->state,
            'pin_code' => $location->pin_code,
        ])->all();
    }
}
