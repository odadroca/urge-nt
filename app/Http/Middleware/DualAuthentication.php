<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DualAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try session auth first (SPA with cookies)
        if ($request->user()) {
            return $next($request);
        }

        // Fall back to API key auth (Bearer token)
        return app(ApiKeyAuthentication::class)->handle($request, $next);
    }
}
