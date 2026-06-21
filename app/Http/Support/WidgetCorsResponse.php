<?php

namespace App\Http\Support;

use App\Services\Widget\WidgetOriginAllowlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WidgetCorsResponse
{
    public function __construct(
        private readonly WidgetOriginAllowlist $originAllowlist,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function json(Request $request, array $data, int $status = 200, array $headers = []): JsonResponse
    {
        return $this->withCorsHeaders($request, response()->json($data, $status, $headers));
    }

    public function withCorsHeaders(Request $request, Response $response): Response
    {
        $allowedOrigin = $this->originAllowlist->allowedOrigin(
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        );

        if ($allowedOrigin === null) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-Widget-Key');
        $response->headers->set('Access-Control-Max-Age', '600');

        return $response;
    }
}
