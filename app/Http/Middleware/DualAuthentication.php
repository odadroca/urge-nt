<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DualAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try web guard (session auth from SPA cookies)
        if (Auth::guard('web')->check()) {
            Auth::setUser(Auth::guard('web')->user());
            return $next($request);
        }

        // Fall back to API key auth (Bearer token)
        return app(ApiKeyAuthentication::class)->handle($request, $next);
    }
}
