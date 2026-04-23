<?php

namespace App\Services;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

class OAuthService
{
    public function generateAuthorizationCode(
        User $user,
        string $clientId,
        string $redirectUri,
        string $scope,
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
        ?string $resource = null,
    ): string {
        $code = Str::random(64);

        OAuthAuthorizationCode::create([
            'code'                  => hash('sha256', $code),
            'client_id'             => $clientId,
            'user_id'               => $user->id,
            'redirect_uri'          => $redirectUri,
            'scope'                 => $scope,
            'code_challenge'        => $codeChallenge ?? '',
            'code_challenge_method' => $codeChallengeMethod ?? 'S256',
            'resource'              => $resource,
            'expires_at'            => now()->addSeconds(config('urge.oauth.code_ttl', 600)),
        ]);

        return $code;
    }

    public function exchangeCode(
        string $code,
        string $codeVerifier,
        string $clientId,
        string $redirectUri,
        string $clientSecret = '',
    ): ?OAuthAccessToken {
        $authCode = OAuthAuthorizationCode::where('code', hash('sha256', $code))
            ->where('client_id', $clientId)
            ->where('redirect_uri', $redirectUri)
            ->first();

        if (!$authCode || $authCode->isExpired()) {
            return null;
        }

        // Authenticate client: PKCE (public) or client_secret (confidential)
        $client = $this->findClient($clientId);
        if ($client && $client->client_secret) {
            // Confidential client — validate secret
            if (!hash_equals($client->client_secret, hash('sha256', $clientSecret))) {
                return null;
            }
        } elseif ($codeVerifier) {
            // Public client — validate PKCE
            if (!$this->validatePkce($codeVerifier, $authCode->code_challenge, $authCode->code_challenge_method)) {
                return null;
            }
        } else {
            return null;
        }

        $rawToken = Str::random(64);
        $token = OAuthAccessToken::create([
            'token'      => hash('sha256', $rawToken),
            'user_id'    => $authCode->user_id,
            'client_id'  => $authCode->client_id,
            'scope'      => $authCode->scope,
            'expires_at' => now()->addSeconds(config('urge.oauth.token_ttl', 3600)),
        ]);

        $rawRefreshToken = Str::random(64);
        OAuthRefreshToken::create([
            'token'           => hash('sha256', $rawRefreshToken),
            'user_id'         => $authCode->user_id,
            'client_id'       => $authCode->client_id,
            'scope'           => $authCode->scope,
            'access_token_id' => $token->id,
            'expires_at'      => now()->addSeconds(config('urge.oauth.refresh_token_ttl', 2592000)),
        ]);

        $authCode->delete();

        $token->raw_token = $rawToken;
        $token->raw_refresh_token = $rawRefreshToken;

        return $token;
    }

    public function refreshToken(
        string $rawRefreshToken,
        string $clientId,
        ?string $requestedScope = null,
    ): ?OAuthAccessToken {
        $refreshToken = OAuthRefreshToken::where('token', hash('sha256', $rawRefreshToken))->first();

        if (!$refreshToken || $refreshToken->isExpired()) {
            return null;
        }

        if ($refreshToken->client_id !== $clientId) {
            return null;
        }

        $scope = $refreshToken->scope;

        if ($requestedScope !== null && $requestedScope !== '') {
            $grantedScopes = explode(' ', $refreshToken->scope);
            $requestedParts = explode(' ', $requestedScope);
            foreach ($requestedParts as $s) {
                if (!in_array($s, $grantedScopes)) {
                    return null;
                }
            }
            $scope = $requestedScope;
        }

        $rawToken = Str::random(64);
        $newAccessToken = OAuthAccessToken::create([
            'token'      => hash('sha256', $rawToken),
            'user_id'    => $refreshToken->user_id,
            'client_id'  => $refreshToken->client_id,
            'scope'      => $scope,
            'expires_at' => now()->addSeconds(config('urge.oauth.token_ttl', 3600)),
        ]);

        $rawNewRefreshToken = Str::random(64);
        OAuthRefreshToken::create([
            'token'           => hash('sha256', $rawNewRefreshToken),
            'user_id'         => $refreshToken->user_id,
            'client_id'       => $refreshToken->client_id,
            'scope'           => $scope,
            'access_token_id' => $newAccessToken->id,
            'expires_at'      => now()->addSeconds(config('urge.oauth.refresh_token_ttl', 2592000)),
        ]);

        // Clean up old tokens
        OAuthAccessToken::where('id', $refreshToken->access_token_id)->delete();
        $refreshToken->delete();

        $newAccessToken->raw_token = $rawToken;
        $newAccessToken->raw_refresh_token = $rawNewRefreshToken;

        return $newAccessToken;
    }

    public function findByToken(string $rawToken): ?OAuthAccessToken
    {
        $token = OAuthAccessToken::where('token', hash('sha256', $rawToken))
            ->with('user')
            ->first();

        if (!$token || $token->isExpired()) {
            return null;
        }

        return $token;
    }

    public function validatePkce(string $verifier, string $challenge, string $method): bool
    {
        if ($method !== 'S256') {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    public function validateScope(string $requested): bool
    {
        $allowed = config('urge.oauth.scopes', []);
        $parts = explode(' ', $requested);

        foreach ($parts as $scope) {
            if (!in_array($scope, $allowed)) {
                return false;
            }
        }

        return true;
    }

    public function fetchClientMetadata(string $clientId): ?array
    {
        if (!filter_var($clientId, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($clientId);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['redirect_uris']) && is_array($data['redirect_uris'])) {
                    return $data;
                }
            }
        } catch (\Exception $e) {
            // Client metadata not fetchable
        }

        return null;
    }

    public function findClient(string $clientId): ?OAuthClient
    {
        return OAuthClient::where('client_id', $clientId)->first();
    }

    public function validateRedirectUri(string $clientId, string $redirectUri): bool
    {
        // 1. Check registered client (DB lookup)
        $client = $this->findClient($clientId);
        if ($client) {
            return in_array($redirectUri, $client->redirect_uris);
        }

        // 2. Fetch client metadata from URL (existing logic)
        $metadata = $this->fetchClientMetadata($clientId);
        if ($metadata && isset($metadata['redirect_uris'])) {
            return in_array($redirectUri, $metadata['redirect_uris']);
        }

        // 3. Allow localhost/loopback (existing fallback)
        $parsed = parse_url($redirectUri);
        $host = $parsed['host'] ?? '';

        return in_array($host, ['localhost', '127.0.0.1', '[::1]']);
    }
}
