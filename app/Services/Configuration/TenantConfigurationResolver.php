<?php

namespace App\Services\Configuration;

use App\Enums\Configuration\DayOfWeek;
use App\Enums\Configuration\WidgetPosition;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\TenantWidgetSettings;

class TenantConfigurationResolver
{
    public function settings(Tenant $tenant): TenantSettings
    {
        return TenantSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'display_name' => $tenant->name,
                'assistant_name' => null,
                'primary_color' => '#2563EB',
                'ai_disclosure_enabled' => true,
                'ai_disclosure_message' => 'You are chatting with an AI-powered assistant.',
                'default_locale' => $tenant->locale ?? 'en',
                'supported_locales' => [$tenant->locale ?? 'en'],
                'human_transfer_enabled' => true,
                'human_transfer_label' => 'Speak to a counsellor',
            ],
        );
    }

    public function widgetSettings(Tenant $tenant): TenantWidgetSettings
    {
        return TenantWidgetSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'welcome_message' => (string) config('widget.default_welcome_message', 'Hello! I am your AI counsellor. Ask me about services, admission, eligibility, fees, documents, or counselling support.'),
                'offline_message' => 'We are currently offline. Leave your details and we will get back to you.',
                'offline_form_enabled' => true,
                'widget_position' => WidgetPosition::BottomRight->value,
                'welcome_delay_seconds' => 0,
            ],
        );
    }

    public function ensureDefaultOfficeHours(Tenant $tenant): void
    {
        if ($tenant->officeHours()->exists()) {
            return;
        }

        foreach (DayOfWeek::cases() as $day) {
            $tenant->officeHours()->create([
                'day_of_week' => $day->value,
                'opens_at' => '09:00:00',
                'closes_at' => '18:00:00',
                'is_closed' => in_array($day, [DayOfWeek::Sunday], true),
            ]);
        }
    }
}
