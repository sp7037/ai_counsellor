<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIntegrationManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (is_string($tenant)) {
            $tenant = Tenant::query()->where('uuid', $tenant)->first();
        }

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null || ! $user->tenantRoleFor($tenant)?->canManageIntegrations()) {
            abort(403);
        }

        return $next($request);
    }
}
