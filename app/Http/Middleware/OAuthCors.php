<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuthCors
{
    public function handle(Request $request, Closure $next): Response
    {
        // Handle CORS preflight
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->corsHeaders($request));
        }

        $response = $next($request);

        foreach ($this->corsHeaders($request) as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    private function corsHeaders(Request $request): array
    {
        $origin = $request->header('Origin', '*');

        return [
            'Access-Control-Allow-Origin'  => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age'       => '86400',
        ];
    }
}
