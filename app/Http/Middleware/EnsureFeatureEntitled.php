<?php

namespace App\Http\Middleware;

use App\Enums\Billing\PlanFeature;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Services\Billing\EntitlementResolver;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEntitled
{
    public function __construct(
        private readonly EntitlementResolver $entitlements,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            abort(403);
        }

        try {
            $this->entitlements->assertAllowed($tenant, PlanFeature::from($feature));
        } catch (EntitlementDeniedException $exception) {
            if ($request->expectsJson()) {
                abort(403, $exception->getMessage());
            }

            return redirect()
                ->route('tenant.subscription', $tenant)
                ->with('subscription_restriction', $exception->result->outcome->value);
        }

        return $next($request);
    }
}
