<?php

namespace App\Services\Widget;

class OriginValidator
{
    public function extractOriginDomain(?string $origin, ?string $referer = null): ?string
    {
        $candidate = $origin ?: $referer;

        if ($candidate === null || $candidate === '') {
            return null;
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }

    public function isAllowedLocalOrigin(?string $origin): bool
    {
        $configured = config('widget.allow_local_origins');

        if ($configured === null) {
            $allowed = app()->environment('local', 'testing');
        } else {
            $allowed = filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        if (! $allowed) {
            return false;
        }

        if ($origin === null) {
            return false;
        }

        return in_array($origin, config('widget.local_origins', []), true);
    }

    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        $domain = explode('/', $domain)[0];
        $domain = explode(':', $domain)[0];

        if ($domain === '' || str_contains($domain, '*')) {
            return '';
        }

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    public function matchesAllowedDomain(string $allowedDomain, string $originHost): bool
    {
        return $this->normalizeDomain($allowedDomain) === $this->normalizeDomain($originHost);
    }
}
