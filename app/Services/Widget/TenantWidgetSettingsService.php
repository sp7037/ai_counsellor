<?php

namespace App\Services\Widget;

use App\Models\Tenant;
use App\Models\TenantWidgetSettings;

class TenantWidgetSettingsService
{
    public function update(Tenant $tenant, array $attributes): TenantWidgetSettings
    {
        $settings = TenantWidgetSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'welcome_message' => 'Hello! How can we help you today?',
                'offline_message' => 'We are currently offline. Leave your details and we will get back to you.',
                'offline_form_enabled' => true,
            ],
        );

        $settings->update([
            'welcome_message' => $attributes['welcome_message'] ?? $settings->welcome_message,
            'offline_message' => $attributes['offline_message'] ?? $settings->offline_message,
            'offline_form_enabled' => $attributes['offline_form_enabled'] ?? $settings->offline_form_enabled,
        ]);

        return $settings->fresh();
    }
}
