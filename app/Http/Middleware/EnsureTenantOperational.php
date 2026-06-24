<?php

namespace App\Http\Middleware;

use App\Enums\Billing\SubscriptionStatus;
use App\Services\Billing\EntitlementResolver;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantOperational
{
    public function __construct(private readonly EntitlementResolver $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        if ($request->routeIs('tenant.subscription')) {
            return $next($request);
        }

        $status = $this->entitlements->effectiveSubscriptionStatus($tenant);

        if ($status === null) {
            if ($request->routeIs(
                'tenant.dashboard',
                'tenant.select',
                'tenant.counsellors.index',
                'tenant.counsellors.create',
            )) {
                return $next($request);
            }

            return $this->restricted($request, 'no_subscription');
        }

        if (in_array($status, [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled, SubscriptionStatus::PastDue], true)) {
            if ($request->routeIs('tenant.dashboard')) {
                return $next($request);
            }

            return $this->restricted($request, 'subscription_expired');
        }

        return $next($request);
    }

    private function restricted(Request $request, string $reason): Response
    {
        if ($request->expectsJson()) {
            abort(403, 'Access restricted.');
        }

        $tenant = app(TenantContext::class)->tenant();

        return redirect()
            ->route('tenant.subscription', $tenant)
            ->with('subscription_restriction', $reason);
    }
}
