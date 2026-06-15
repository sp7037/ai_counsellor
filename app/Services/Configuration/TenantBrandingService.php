<?php

namespace App\Services\Configuration;

use App\Enums\Audit\AuditAction;
use App\Enums\Configuration\WidgetPosition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantBrandingService
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

            $before = $this->snapshot($settings, $widgetSettings, $tenant);

            if (array_key_exists('timezone', $attributes)) {
                $tenant->update([
                    'timezone' => $this->validator->validateTimezone((string) $attributes['timezone']),
                ]);
            }

            if (array_key_exists('locale', $attributes)) {
                $tenant->update([
                    'locale' => $this->validator->validateLocale((string) $attributes['locale']),
                ]);
            }

            $settings->update([
                'display_name' => $this->validator->sanitizePlainText(
                    $attributes['display_name'] ?? $settings->display_name,
                    config('configuration.max_display_name_length', 120),
                ) ?? $tenant->name,
                'primary_color' => isset($attributes['primary_color'])
                    ? $this->validator->validateHexColor('primary_color', $attributes['primary_color'])
                    : $settings->primary_color,
                'accent_color' => isset($attributes['accent_color']) && $attributes['accent_color'] !== ''
                    ? $this->validator->validateHexColor('accent_color', $attributes['accent_color'])
                    : null,
            ]);

            $widgetSettings->update([
                'widget_position' => isset($attributes['widget_position'])
                    ? WidgetPosition::from((string) $attributes['widget_position'])->value
                    : $widgetSettings->widget_position?->value,
            ]);

            $after = $this->snapshot($settings->fresh(), $widgetSettings->fresh(), $tenant->fresh());

            $this->auditLogger->log(
                AuditAction::BrandingUpdated,
                $settings,
                $tenant->id,
                ['before' => $before, 'after' => $after],
                $actor,
            );
        });
    }

    public function uploadLogo(Tenant $tenant, UploadedFile $file, User $actor): void
    {
        $this->assertSafeImage($file);

        DB::transaction(function () use ($tenant, $file, $actor): void {
            $settings = $this->resolver->settings($tenant);
            $beforePath = $settings->logo_path;

            $extension = match ($file->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw ValidationException::withMessages(['logo' => 'Unsupported image type.']),
            };

            $path = sprintf(
                'tenant-logos/%s/%s.%s',
                $tenant->uuid,
                Str::lower(Str::random(40)),
                $extension,
            );

            Storage::disk('public')->putFileAs(
                dirname($path),
                $file,
                basename($path),
            );

            $settings->update(['logo_path' => $path]);

            if ($beforePath !== null) {
                Storage::disk('public')->delete($beforePath);
            }

            $this->auditLogger->log(
                AuditAction::LogoUpdated,
                $settings,
                $tenant->id,
                ['logo_path' => $path],
                $actor,
            );
        });
    }

    public function removeLogo(Tenant $tenant, User $actor): void
    {
        DB::transaction(function () use ($tenant, $actor): void {
            $settings = $this->resolver->settings($tenant);

            if ($settings->logo_path === null) {
                return;
            }

            $beforePath = $settings->logo_path;
            Storage::disk('public')->delete($beforePath);
            $settings->update(['logo_path' => null]);

            $this->auditLogger->log(
                AuditAction::LogoRemoved,
                $settings,
                $tenant->id,
                ['before' => ['logo_path' => $beforePath]],
                $actor,
            );
        });
    }

    private function assertSafeImage(UploadedFile $file): void
    {
        $maxKb = config('configuration.max_logo_size_kb', 2048);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['logo' => 'Logo must be smaller than '.$maxKb.' KB.']);
        }

        if (! in_array($file->getMimeType(), config('configuration.allowed_logo_mimes', []), true)) {
            throw ValidationException::withMessages(['logo' => 'Only JPEG, PNG or WebP logos are allowed.']);
        }

        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw ValidationException::withMessages(['logo' => 'The uploaded file is not a valid image.']);
        }
    }

    private function snapshot($settings, $widgetSettings, Tenant $tenant): array
    {
        return [
            'display_name' => $settings->display_name,
            'primary_color' => $settings->primary_color,
            'accent_color' => $settings->accent_color,
            'logo_path' => $settings->logo_path,
            'widget_position' => $widgetSettings->widget_position?->value,
            'timezone' => $tenant->timezone,
            'locale' => $tenant->locale,
        ];
    }
}
