<?php

namespace App\Services\Configuration;

use App\Enums\Audit\AuditAction;
use App\Enums\Configuration\WidgetPosition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class TenantAssistantConfigurationService
{
    public function __construct(
        private readonly TenantConfigurationResolver $resolver,
        private readonly ConfigurationValidator $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function update(Tenant $tenant, array $attributes, User $actor): void
    {
        DB::transaction(function () use ($tenant, $attributes, $actor): void {
            $settings = $this->resolver->settings($tenant);
            $widgetSettings = $this->resolver->widgetSettings($tenant);

            $before = [
                'assistant_name' => $settings->assistant_name,
                'assistant_title' => $settings->assistant_title,
                'welcome_message' => $widgetSettings->welcome_message,
                'offline_message' => $widgetSettings->offline_message,
                'offline_form_enabled' => $widgetSettings->offline_form_enabled,
                'ai_disclosure_enabled' => $settings->ai_disclosure_enabled,
                'human_transfer_enabled' => $settings->human_transfer_enabled,
                'default_locale' => $settings->default_locale,
                'supported_locales' => $settings->supported_locales,
            ];

            $settings->update([
                'assistant_name' => $this->validator->sanitizePlainText(
                    $attributes['assistant_name'] ?? $settings->assistant_name,
                    config('configuration.max_assistant_name_length', 120),
                ),
                'assistant_title' => $this->validator->sanitizePlainText(
                    $attributes['assistant_title'] ?? $settings->assistant_title,
                    config('configuration.max_assistant_name_length', 120),
                ),
                'consent_text' => $this->validator->sanitizePlainText(
                    $attributes['consent_text'] ?? $settings->consent_text,
                    config('configuration.max_consent_text_length', 4000),
                ),
                'consent_version' => $this->validator->sanitizePlainText(
                    $attributes['consent_version'] ?? $settings->consent_version,
                    32,
                ),
                'ai_disclosure_enabled' => (bool) ($attributes['ai_disclosure_enabled'] ?? $settings->ai_disclosure_enabled),
                'ai_disclosure_message' => $this->validator->sanitizePlainText(
                    $attributes['ai_disclosure_message'] ?? $settings->ai_disclosure_message,
                    config('configuration.max_message_length', 500),
                ) ?? 'You are chatting with an AI-powered assistant.',
                'human_transfer_enabled' => (bool) ($attributes['human_transfer_enabled'] ?? $settings->human_transfer_enabled),
                'human_transfer_label' => $this->validator->sanitizePlainText(
                    $attributes['human_transfer_label'] ?? $settings->human_transfer_label,
                    120,
                ) ?? 'Speak to a counsellor',
                'human_transfer_message' => $this->validator->sanitizePlainText(
                    $attributes['human_transfer_message'] ?? $settings->human_transfer_message,
                    config('configuration.max_message_length', 500),
                ),
                'default_locale' => $this->validator->validateLocale(
                    (string) ($attributes['default_locale'] ?? $settings->default_locale),
                ),
                'supported_locales' => $this->validator->validateSupportedLocales(
                    $attributes['supported_locales'] ?? $settings->supported_locales ?? ['en'],
                ),
            ]);

            $widgetSettings->update([
                'welcome_message' => $this->validator->sanitizePlainText(
                    $attributes['welcome_message'] ?? $widgetSettings->welcome_message,
                    config('configuration.max_message_length', 500),
                ) ?? 'Hello! How can we help you today?',
                'offline_message' => $this->validator->sanitizePlainText(
                    $attributes['offline_message'] ?? $widgetSettings->offline_message,
                    config('configuration.max_message_length', 500),
                ) ?? 'We are currently offline.',
                'offline_form_enabled' => (bool) ($attributes['offline_form_enabled'] ?? $widgetSettings->offline_form_enabled),
                'welcome_delay_seconds' => min(30, max(0, (int) ($attributes['welcome_delay_seconds'] ?? $widgetSettings->welcome_delay_seconds))),
                'widget_position' => isset($attributes['widget_position'])
                    ? WidgetPosition::from((string) $attributes['widget_position'])->value
                    : $widgetSettings->widget_position?->value,
            ]);

            $after = [
                'assistant_name' => $settings->fresh()->assistant_name,
                'assistant_title' => $settings->fresh()->assistant_title,
                'welcome_message' => $widgetSettings->fresh()->welcome_message,
                'offline_message' => $widgetSettings->fresh()->offline_message,
                'offline_form_enabled' => $widgetSettings->fresh()->offline_form_enabled,
                'ai_disclosure_enabled' => $settings->fresh()->ai_disclosure_enabled,
                'human_transfer_enabled' => $settings->fresh()->human_transfer_enabled,
                'default_locale' => $settings->fresh()->default_locale,
                'supported_locales' => $settings->fresh()->supported_locales,
            ];

            $this->auditLogger->log(
                AuditAction::ConfigurationUpdated,
                $settings,
                $tenant->id,
                ['before' => $before, 'after' => $after],
                $actor,
            );
        });
    }
}
