<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthentication
{
    public function __construct(private ApiKeyService $apiKeyService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Missing API key. Provide a Bearer token in the Authorization header.'], 401);
        }

        $apiKey = $this->apiKeyService->findByToken($token);

        if (!$apiKey) {
            return response()->json(['error' => 'Invalid or expired API key.'], 401);
        }

        // Rate limiting
        $limit = config('urge.api_rate_limit', 60);
        $window = config('urge.api_rate_window', 60);
        $cacheKey = "api_rate:{$apiKey->id}";

        $hits = Cache::get($cacheKey, 0);
        if ($hits >= $limit) {
            return response()->json([
                'error' => 'Rate limit exceeded.',
                'retry_after' => $window,
            ], 429);
        }
        Cache::put($cacheKey, $hits + 1, $window);

        // Update last used
        $apiKey->update(['last_used_at' => now()]);

        // Attach to request
        $request->attributes->set('api_key', $apiKey);
        $request->setUserResolver(fn () => $apiKey->user);

        return $next($request);
    }
}
