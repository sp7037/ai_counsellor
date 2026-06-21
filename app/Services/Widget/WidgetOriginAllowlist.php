<?php

namespace App\Services\Widget;

class WidgetOriginAllowlist
{
    public function __construct(
        private readonly OriginValidator $originValidator,
        private readonly WidgetDomainMatcher $domainMatcher,
    ) {}

    public function allowedOrigin(?string $origin, ?string $referer = null): ?string
    {
        if ($origin === null || trim($origin) === '') {
            return null;
        }

        if ($this->originValidator->isAllowedLocalOrigin($origin)) {
            return $origin;
        }

        $originHost = $this->originValidator->extractOriginDomain($origin, $referer);

        if ($originHost === null) {
            return null;
        }

        return $this->domainMatcher->isVerifiedForAnyTenant($originHost) ? $origin : null;
    }
}
