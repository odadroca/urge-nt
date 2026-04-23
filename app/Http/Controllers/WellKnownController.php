<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WellKnownController
{
    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource'                  => url('/api/v1/mcp'),
            'authorization_servers'     => [url('/')],
            'scopes_supported'          => config('urge.oauth.scopes', []),
            'bearer_methods_supported'  => ['header'],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json($this->authServerMetadata());
    }

    public function openIdConfiguration(): JsonResponse
    {
        return response()->json($this->authServerMetadata());
    }

    private function authServerMetadata(): array
    {
        return [
            'issuer'                            => url('/'),
            'authorization_endpoint'            => url('/oauth/authorize'),
            'token_endpoint'                    => url('/oauth/token'),
            'scopes_supported'                  => config('urge.oauth.scopes', []),
            'response_types_supported'          => ['code'],
            'grant_types_supported'             => ['authorization_code', 'refresh_token'],
            'registration_endpoint'             => url('/oauth/register'),
            'code_challenge_methods_supported'  => ['S256'],
        ];
    }
}
