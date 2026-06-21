<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use App\Services\Widget\OriginValidator;
use App\Services\Widget\WidgetSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWidgetSession
{
    public function __construct(
        private readonly WidgetSessionService $sessionService,
        private readonly TenantContext $tenantContext,
        private readonly OriginValidator $originValidator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return response()->json(['message' => 'Session token required.'], 401);
        }

        $session = $this->sessionService->findByToken($token);

        if ($session === null) {
            return response()->json(['message' => 'Invalid or expired session.'], 401);
        }

        $tenant = $session->tenant;

        if (! $tenant->allowsTenantAccess()) {
            return response()->json(['message' => 'Organisation unavailable.'], 403);
        }

        $originDomain = $this->originValidator->extractOriginDomain(
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        );

        if ($originDomain !== null
            && $this->originValidator->normalizeDomain($originDomain) !== $this->originValidator->normalizeDomain($session->origin_domain)) {
            $localAllowed = config('widget.allow_local_origins')
                && in_array($request->headers->get('Origin'), config('widget.local_origins', []), true);

            if (! $localAllowed) {
                return response()->json(['message' => 'Origin mismatch.'], 403);
            }
        }

        $this->tenantContext->setFromWidgetGateway($tenant);
        $this->tenantContext->enforceIsolation();
        $this->sessionService->touch($session);

        $request->attributes->set('widget_session', $session->fresh());

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $authorization = $request->bearerToken();

        if (is_string($authorization) && $authorization !== '') {
            return $authorization;
        }

        return null;
    }
}
