<?php

namespace App\Services\Configuration;

use App\Enums\Configuration\CatalogueStatus;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
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

        return [
            'branding' => [
                'display_name' => $settings->display_name ?: $tenant->name,
                'assistant_name' => $settings->assistant_name ?: 'Counsellor',
                'assistant_title' => $settings->assistant_title,
                'primary_color' => $settings->primary_color,
                'accent_color' => $settings->accent_color,
                'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
                'widget_position' => $widgetSettings->widget_position?->value ?? 'bottom_right',
            ],
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
                'label' => $settings->human_transfer_label,
                'message' => $settings->human_transfer_message,
            ],
            'availability' => $availability,
            'catalogue' => [
                'services' => $this->mapCatalogue(Service::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
                'courses' => $this->mapCourses(Course::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
                'institutions' => $this->mapInstitutions(Institution::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
                'locations' => $this->mapLocations(Location::query()->where('status', CatalogueStatus::Active->value)->orderBy('sort_order')->get()),
            ],
        ];
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
