<?php

namespace App\Services\Widget;

use App\Enums\Widget\WidgetKeyStatus;
use App\Exceptions\Widget\WidgetGatewayDeniedException;
use App\Models\Tenant;
use App\Models\WidgetKey;

class WidgetTenantResolver
{
    public function __construct(
        private readonly OriginValidator $originValidator,
        private readonly WidgetDomainMatcher $domainMatcher,
    ) {}

    public function resolve(string $publicKey, ?string $origin, ?string $referer = null): array
    {
        $widgetKey = WidgetKey::query()
            ->withoutGlobalScopes()
            ->where('public_key', $publicKey)
            ->first();

        if ($widgetKey === null) {
            throw new WidgetGatewayDeniedException('Widget key is invalid.', 'invalid_widget_key');
        }

        if ($widgetKey->status !== WidgetKeyStatus::Active) {
            throw new WidgetGatewayDeniedException('Widget key is inactive.', 'inactive_widget_key');
        }

        $tenant = $widgetKey->tenant;

        if (! $tenant->allowsTenantAccess()) {
            throw new WidgetGatewayDeniedException('Organisation unavailable.', 'organisation_unavailable');
        }

        $originDomain = $this->domainMatcher->canonicalHost($origin, $referer);

        if ($originDomain === null) {
            throw new WidgetGatewayDeniedException('Origin required.', 'origin_required');
        }

        if (! $this->domainMatcher->isAllowedForTenant($tenant, $originDomain, $origin)) {
            throw new WidgetGatewayDeniedException('Widget domain is not allowed.', 'domain_not_allowed');
        }

        return [
            'tenant' => $tenant,
            'widget_key' => $widgetKey,
            'origin_domain' => $originDomain,
        ];
    }

    public function domainIsAllowed(Tenant $tenant, string $originDomain, ?string $fullOrigin = null): bool
    {
        return $this->domainMatcher->isAllowedForTenant($tenant, $originDomain, $fullOrigin);
    }
}
