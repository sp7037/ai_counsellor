<?php

namespace App\Http\Middleware;

use App\Services\Billing\EntitlementResolver;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCounsellorSubscription
{
    public function __construct(
        private readonly EntitlementResolver $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            abort(403);
        }

        $status = $this->entitlements->effectiveSubscriptionStatus($tenant);

        if ($status !== null && ! $status->allowsOperationalAccess()) {
            abort(403, 'Your organisation\'s access to this feature is currently unavailable. Please contact your administrator.');
        }

        return $next($request);
    }
}
