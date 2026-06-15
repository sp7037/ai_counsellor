<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleWidgetCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->addCorsHeaders(response('', 204), $request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($response, $request);
    }

    private function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Widget-Key');
        $response->headers->set('Access-Control-Max-Age', '600');

        return $response;
    }
}
