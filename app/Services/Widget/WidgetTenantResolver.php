<?php

namespace App\Services\Widget;

use App\Enums\Widget\TenantDomainStatus;
use App\Enums\Widget\WidgetKeyStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\WidgetKey;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WidgetTenantResolver
{
    public function __construct(
        private readonly OriginValidator $originValidator,
    ) {}

    public function resolve(string $publicKey, ?string $origin, ?string $referer = null): array
    {
        $widgetKey = WidgetKey::query()
            ->where('public_key', $publicKey)
            ->where('status', WidgetKeyStatus::Active->value)
            ->first();

        if ($widgetKey === null) {
            throw new AccessDeniedHttpException('Invalid widget key.');
        }

        $tenant = $widgetKey->tenant;

        if (! $tenant->allowsTenantAccess()) {
            throw new AccessDeniedHttpException('Organisation unavailable.');
        }

        $originDomain = $this->originValidator->extractOriginDomain($origin, $referer);

        if ($originDomain === null) {
            throw new AccessDeniedHttpException('Origin required.');
        }

        if (! $this->domainIsAllowed($tenant, $originDomain, $origin)) {
            throw new AccessDeniedHttpException('Domain not allowed.');
        }

        return [
            'tenant' => $tenant,
            'widget_key' => $widgetKey,
            'origin_domain' => $originDomain,
        ];
    }

    public function domainIsAllowed(Tenant $tenant, string $originDomain, ?string $fullOrigin = null): bool
    {
        if ($fullOrigin !== null && $this->originValidator->isAllowedLocalOrigin($fullOrigin)) {
            return true;
        }

        return TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('domain', $originDomain)
            ->where('status', TenantDomainStatus::Verified->value)
            ->exists();
    }
}
