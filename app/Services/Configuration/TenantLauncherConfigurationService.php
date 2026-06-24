<?php

namespace App\Services\Configuration;

use App\Enums\Audit\AuditAction;
use App\Enums\Configuration\LauncherAnimation;
use App\Enums\Configuration\LauncherMode;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantLauncherConfigurationService
{
    public function __construct(
        private readonly TenantConfigurationResolver $resolver,
        private readonly ConfigurationValidator $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function update(Tenant $tenant, array $attributes, User $actor): void
    {
        DB::transaction(function () use ($tenant, $attributes, $actor): void {
            $widgetSettings = $this->resolver->widgetSettings($tenant);
            $before = $this->snapshot($widgetSettings);

            $payload = [];

            if (array_key_exists('launcher_mode', $attributes)) {
                $payload['launcher_mode'] = LauncherMode::from((string) $attributes['launcher_mode'])->value;
            }

            foreach ([
                'launcher_card_title' => config('configuration.max_launcher_card_title_length', 120),
                'launcher_card_subtitle' => config('configuration.max_launcher_card_subtitle_length', 280),
                'launcher_card_cta_text' => config('configuration.max_launcher_card_cta_length', 60),
                'launcher_card_trust_text' => config('configuration.max_launcher_card_trust_length', 80),
            ] as $field => $maxLength) {
                if (array_key_exists($field, $attributes)) {
                    $payload[$field] = $this->validator->sanitizePlainText(
                        $attributes[$field] !== null ? (string) $attributes[$field] : null,
                        $maxLength,
                    );
                }
            }

            if (array_key_exists('launcher_card_delay_seconds', $attributes)) {
                $payload['launcher_card_delay_seconds'] = $this->boundDelay($attributes['launcher_card_delay_seconds']);
            }

            if (array_key_exists('launcher_card_dismiss_hours', $attributes)) {
                $payload['launcher_card_dismiss_hours'] = $this->boundDismissHours($attributes['launcher_card_dismiss_hours']);
            }

            if (array_key_exists('launcher_card_animation', $attributes)) {
                $value = $attributes['launcher_card_animation'];
                $payload['launcher_card_animation'] = $value === null || $value === ''
                    ? null
                    : LauncherAnimation::from((string) $value)->value;
            }

            if ($payload !== []) {
                $widgetSettings->update($payload);
            }

            $this->auditLogger->log(
                AuditAction::LauncherCardUpdated,
                $widgetSettings->fresh(),
                $tenant->id,
                ['before' => $before, 'after' => $this->snapshot($widgetSettings->fresh())],
                $actor,
            );
        });
    }

    public function uploadCardImage(Tenant $tenant, UploadedFile $file, User $actor): void
    {
        $this->assertSafeImage($file);

        DB::transaction(function () use ($tenant, $file, $actor): void {
            $widgetSettings = $this->resolver->widgetSettings($tenant);
            $beforePath = $widgetSettings->launcher_card_image_path;

            $extension = match ($file->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw ValidationException::withMessages(['cardImage' => 'Unsupported image type.']),
            };

            $path = sprintf(
                'tenant-launcher-cards/%s/%s.%s',
                $tenant->uuid,
                Str::lower(Str::random(40)),
                $extension,
            );

            Storage::disk('public')->putFileAs(
                dirname($path),
                $file,
                basename($path),
            );

            $widgetSettings->update(['launcher_card_image_path' => $path]);

            if ($beforePath !== null) {
                Storage::disk('public')->delete($beforePath);
            }

            $this->auditLogger->log(
                AuditAction::LauncherCardImageUpdated,
                $widgetSettings->fresh(),
                $tenant->id,
                ['launcher_card_image_path' => $path],
                $actor,
            );
        });
    }

    public function removeCardImage(Tenant $tenant, User $actor): void
    {
        DB::transaction(function () use ($tenant, $actor): void {
            $widgetSettings = $this->resolver->widgetSettings($tenant);

            if ($widgetSettings->launcher_card_image_path === null) {
                return;
            }

            $beforePath = $widgetSettings->launcher_card_image_path;
            Storage::disk('public')->delete($beforePath);
            $widgetSettings->update(['launcher_card_image_path' => null]);

            $this->auditLogger->log(
                AuditAction::LauncherCardImageRemoved,
                $widgetSettings->fresh(),
                $tenant->id,
                ['before' => ['launcher_card_image_path' => $beforePath]],
                $actor,
            );
        });
    }

    private function assertSafeImage(UploadedFile $file): void
    {
        $maxKb = config('configuration.max_logo_size_kb', 2048);

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['cardImage' => 'Image must be smaller than '.$maxKb.' KB.']);
        }

        if (! in_array($file->getMimeType(), config('configuration.allowed_logo_mimes', []), true)) {
            throw ValidationException::withMessages(['cardImage' => 'Only JPEG, PNG or WebP images are allowed.']);
        }

        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw ValidationException::withMessages(['cardImage' => 'The uploaded file is not a valid image.']);
        }
    }

    private function boundDelay(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(30, (int) $value));
    }

    private function boundDismissHours(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(3, min(10, (int) $value));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot($widgetSettings): array
    {
        return [
            'launcher_mode' => $widgetSettings->launcher_mode?->value,
            'launcher_card_image_path' => $widgetSettings->launcher_card_image_path,
            'launcher_card_title' => $widgetSettings->launcher_card_title,
            'launcher_card_subtitle' => $widgetSettings->launcher_card_subtitle,
            'launcher_card_cta_text' => $widgetSettings->launcher_card_cta_text,
            'launcher_card_trust_text' => $widgetSettings->launcher_card_trust_text,
            'launcher_card_delay_seconds' => $widgetSettings->launcher_card_delay_seconds,
            'launcher_card_dismiss_hours' => $widgetSettings->launcher_card_dismiss_hours,
            'launcher_card_animation' => $widgetSettings->launcher_card_animation?->value,
        ];
    }
}
