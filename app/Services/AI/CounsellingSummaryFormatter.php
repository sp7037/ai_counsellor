<?php

namespace App\Services\AI;

class CounsellingSummaryFormatter
{
    public function budgetPhrase(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        $value = preg_replace('/^budget\s+(?:is\s+)?(?:around\s+)?/i', '', $value) ?? $value;
        $value = trim(preg_replace('/^around\s+/i', '', $value) ?? $value);

        if ($value !== '' && ! preg_match('/^₹/u', $value) && preg_match('/\d/', $value)) {
            $value = '₹'.$value;
        }

        return 'budget around '.$value;
    }

    public function budgetLabel(?string $raw): ?string
    {
        $phrase = $this->budgetPhrase($raw);

        return $phrase !== null ? ucfirst($phrase) : null;
    }

    public function timelinePhrase(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $value = trim((string) $raw);
        $value = preg_replace('/\s+intake(?:\s+timeline)?$/i', '', $value) ?? $value;

        if (! preg_match('/intake/i', $value)) {
            $value = trim($value).' intake';
        }

        return $value;
    }

    public function timelineLabel(?string $raw): ?string
    {
        $phrase = $this->timelinePhrase($raw);

        return $phrase !== null ? 'Timeline '.$phrase : null;
    }

    public function locationPhrase(?string $cityState, ?string $state = null): ?string
    {
        $value = trim((string) ($cityState ?? ''));

        if ($value === '') {
            $value = trim((string) ($state ?? ''));
        } elseif (filled($state) && ! str_contains(strtolower($value), strtolower((string) $state))) {
            $value = rtrim($value, ',').', '.$state;
        }

        $value = preg_replace('/\s+location$/i', '', $value) ?? $value;

        return $value !== '' ? $value : null;
    }

    public function locationLabel(?string $cityState, ?string $state = null): ?string
    {
        $phrase = $this->locationPhrase($cityState, $state);

        return $phrase !== null ? 'Location '.$phrase : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function countryPhrase(array $metadata, ?string $country = null): ?string
    {
        if (($metadata['country_preference'] ?? null) === 'open_to_suggestions') {
            return 'open to country suggestions';
        }

        $value = trim((string) ($country ?? $metadata['preferred_country'] ?? ''));

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+country(?:\s+interest)?$/i', '', $value) ?? $value;

        return $value.' country interest';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function countryLabel(array $metadata, ?string $country = null): ?string
    {
        if (($metadata['country_preference'] ?? null) === 'open_to_suggestions') {
            return 'Open to country suggestions';
        }

        $value = trim((string) ($country ?? $metadata['preferred_country'] ?? ''));

        return $value !== '' ? 'Country '.$value : null;
    }
}
