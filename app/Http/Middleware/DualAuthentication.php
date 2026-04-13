<?php

namespace App\Http\Middleware;

use App\Services\OAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DualAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Sanctum session auth (SPA)
        if ($request->user()) {
            return $next($request);
        }

        // 2. OAuth access token
        $bearer = $request->bearerToken();
        if ($bearer && !str_starts_with($bearer, config('urge.key_prefix', 'urge_'))) {
            $oauthService = app(OAuthService::class);
            $token = $oauthService->findByToken($bearer);
            if ($token) {
                $request->setUserResolver(fn () => $token->user);
                $request->attributes->set('oauth_token', $token);
                return $next($request);
            }
        }

        // 3. API key auth (legacy Bearer token)
        return app(ApiKeyAuthentication::class)->handle($request, $next);
    }
}
