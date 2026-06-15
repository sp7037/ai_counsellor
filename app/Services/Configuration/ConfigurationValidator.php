<?php

namespace App\Services\Configuration;

use DateTimeZone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfigurationValidator
{
    public function validateHexColor(string $field, ?string $value): string
    {
        $validator = Validator::make(
            [$field => $value],
            [$field => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
            [$field.'.regex' => 'Use a valid hex colour like #2563EB.'],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return strtoupper((string) $value);
    }

    public function validateTimezone(string $timezone): string
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'timezone' => 'Select a valid IANA timezone.',
            ]);
        }

        return $timezone;
    }

    public function validateLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        if (! in_array($locale, config('configuration.supported_locales', ['en']), true)) {
            throw ValidationException::withMessages([
                'locale' => 'This language is not supported.',
            ]);
        }

        return $locale;
    }

    /** @param  array<int, string>  $locales */
    public function validateSupportedLocales(array $locales): array
    {
        $allowed = config('configuration.supported_locales', ['en']);
        $normalized = [];

        foreach ($locales as $locale) {
            $locale = strtolower(trim((string) $locale));
            if ($locale === '') {
                continue;
            }
            if (! in_array($locale, $allowed, true)) {
                throw ValidationException::withMessages([
                    'supportedLocales' => 'One or more selected languages are not supported.',
                ]);
            }
            $normalized[] = $locale;
        }

        return array_values(array_unique($normalized));
    }

    public function sanitizePlainText(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(strip_tags($value));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }
}
