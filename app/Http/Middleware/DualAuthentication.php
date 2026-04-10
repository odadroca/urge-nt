<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DualAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sanctum handles session auth for stateful requests.
        // $request->user() works for session (SPA) auth.
        if ($request->user()) {
            return $next($request);
        }

        // Fall back to API key auth (Bearer token for external consumers)
        return app(ApiKeyAuthentication::class)->handle($request, $next);
    }
}
