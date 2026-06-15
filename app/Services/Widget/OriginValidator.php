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
        if (! config('widget.allow_local_origins')) {
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

        return explode('/', $domain)[0];
    }
}
