<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->clear();

        $tenant = $request->route('tenant');

        if (is_string($tenant)) {
            $tenant = Tenant::query()->where('uuid', $tenant)->first();
        }

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $this->tenantContext->resolveForUser($user, $tenant);

        return $next($request);
    }
}
