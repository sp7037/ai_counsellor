<?php

namespace App\Http\Middleware;

use App\Http\Support\WidgetCorsResponse;
use App\Services\Widget\WidgetOriginAllowlist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleWidgetCors
{
    public function __construct(
        private readonly WidgetOriginAllowlist $originAllowlist,
        private readonly WidgetCorsResponse $corsResponse,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigin = $this->originAllowlist->allowedOrigin(
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        );

        if ($request->isMethod('OPTIONS')) {
            if ($allowedOrigin === null) {
                return response('', Response::HTTP_FORBIDDEN);
            }

            return $this->corsResponse->withCorsHeaders(
                $request,
                response('', Response::HTTP_NO_CONTENT),
            );
        }

        $response = $next($request);

        if ($allowedOrigin === null) {
            return $response;
        }

        return $this->corsResponse->withCorsHeaders($request, $response);
    }
}
