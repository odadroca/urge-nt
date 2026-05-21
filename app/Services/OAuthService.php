<?php

namespace App\Services;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
            'code' => hash('sha256', $code),
            'client_id' => $clientId,
            'user_id' => $user->id,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'code_challenge' => $codeChallenge ?? '',
            'code_challenge_method' => $codeChallengeMethod ?? 'S256',
            'resource' => $resource,
            'expires_at' => now()->addSeconds(config('urge.oauth.code_ttl', 600)),
        ]);

        return $code;
    }

    /**
     * Exchange an authorization code for tokens. Atomic: a row-locked
     * lookup + delete ensures one-and-only-one consumption across
     * concurrent requests (AUTH-08). PKCE is required whenever the
     * code was bound to a challenge, even for confidential clients
     * (AUTH-05).
     */
    public function exchangeCode(
        string $code,
        string $codeVerifier,
        string $clientId,
        string $redirectUri,
        string $clientSecret = '',
    ): ?OAuthAccessToken {
        return DB::transaction(function () use ($code, $codeVerifier, $clientId, $redirectUri, $clientSecret) {
            $authCode = OAuthAuthorizationCode::where('code', hash('sha256', $code))
                ->where('client_id', $clientId)
                ->where('redirect_uri', $redirectUri)
                ->lockForUpdate()
                ->first();

            if (! $authCode || $authCode->isExpired()) {
                return null;
            }

            $client = $this->findClient($clientId);
            $clientIsConfidential = $client && $client->client_secret;

            // Confidential clients must present the correct secret
            if ($clientIsConfidential) {
                if (! hash_equals($client->client_secret, hash('sha256', $clientSecret))) {
                    return null;
                }
            }

            // PKCE: if the code was bound to a challenge, the verifier MUST
            // match — regardless of client confidentiality (AUTH-05).
            if ($authCode->code_challenge !== '') {
                if ($codeVerifier === '' || ! $this->validatePkce($codeVerifier, $authCode->code_challenge, $authCode->code_challenge_method)) {
                    return null;
                }
            } elseif (! $clientIsConfidential) {
                // Public client without a challenge — refuse (no auth proof at all)
                return null;
            }

            // Single-use: delete the code before issuing tokens
            $authCode->delete();

            $rawToken = Str::random(64);
            $token = OAuthAccessToken::create([
                'token' => hash('sha256', $rawToken),
                'user_id' => $authCode->user_id,
                'client_id' => $authCode->client_id,
                'scope' => $authCode->scope,
                'expires_at' => now()->addSeconds(config('urge.oauth.token_ttl', 3600)),
            ]);

            $rawRefreshToken = Str::random(64);
            OAuthRefreshToken::create([
                'token' => hash('sha256', $rawRefreshToken),
                'user_id' => $authCode->user_id,
                'client_id' => $authCode->client_id,
                'scope' => $authCode->scope,
                'access_token_id' => $token->id,
                'expires_at' => now()->addSeconds(config('urge.oauth.refresh_token_ttl', 2592000)),
            ]);

            $token->raw_token = $rawToken;
            $token->raw_refresh_token = $rawRefreshToken;

            return $token;
        });
    }

    /**
     * Refresh-token rotation. Atomic: row-locked single-use enforcement
     * (AUTH-07).
     */
    public function refreshToken(
        string $rawRefreshToken,
        string $clientId,
        ?string $requestedScope = null,
    ): ?OAuthAccessToken {
        return DB::transaction(function () use ($rawRefreshToken, $clientId, $requestedScope) {
            $refreshToken = OAuthRefreshToken::where('token', hash('sha256', $rawRefreshToken))
                ->lockForUpdate()
                ->first();

            if (! $refreshToken || $refreshToken->isExpired()) {
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
                    if (! in_array($s, $grantedScopes)) {
                        return null;
                    }
                }
                $scope = $requestedScope;
            }

            // Delete old tokens BEFORE issuing new ones — single-use guarantee
            // is the lockForUpdate + immediate delete inside the transaction
            OAuthAccessToken::where('id', $refreshToken->access_token_id)->delete();
            $refreshToken->delete();

            $rawToken = Str::random(64);
            $newAccessToken = OAuthAccessToken::create([
                'token' => hash('sha256', $rawToken),
                'user_id' => $refreshToken->user_id,
                'client_id' => $refreshToken->client_id,
                'scope' => $scope,
                'expires_at' => now()->addSeconds(config('urge.oauth.token_ttl', 3600)),
            ]);

            $rawNewRefreshToken = Str::random(64);
            OAuthRefreshToken::create([
                'token' => hash('sha256', $rawNewRefreshToken),
                'user_id' => $refreshToken->user_id,
                'client_id' => $refreshToken->client_id,
                'scope' => $scope,
                'access_token_id' => $newAccessToken->id,
                'expires_at' => now()->addSeconds(config('urge.oauth.refresh_token_ttl', 2592000)),
            ]);

            $newAccessToken->raw_token = $rawToken;
            $newAccessToken->raw_refresh_token = $rawNewRefreshToken;

            return $newAccessToken;
        });
    }

    /**
     * RFC 7009 token revocation. Accepts either an access token or a
     * refresh token; revokes the matching token and its sibling (the
     * other half of the same grant). Returns true if anything was
     * revoked; spec says the endpoint should respond 200 either way,
     * but the caller may want to log misses.
     */
    public function revokeToken(string $rawToken, string $clientId): bool
    {
        $hash = hash('sha256', $rawToken);

        return DB::transaction(function () use ($hash, $clientId) {
            // Access token?
            $access = OAuthAccessToken::where('token', $hash)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();
            if ($access) {
                OAuthRefreshToken::where('access_token_id', $access->id)->delete();
                $access->delete();

                return true;
            }

            // Refresh token?
            $refresh = OAuthRefreshToken::where('token', $hash)
                ->where('client_id', $clientId)
                ->lockForUpdate()
                ->first();
            if ($refresh) {
                OAuthAccessToken::where('id', $refresh->access_token_id)->delete();
                $refresh->delete();

                return true;
            }

            return false;
        });
    }

    public function findByToken(string $rawToken): ?OAuthAccessToken
    {
        $token = OAuthAccessToken::where('token', hash('sha256', $rawToken))
            ->with('user')
            ->first();

        if (! $token || $token->isExpired()) {
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
            if (! in_array($scope, $allowed)) {
                return false;
            }
        }

        return true;
    }

    public function findClient(string $clientId): ?OAuthClient
    {
        return OAuthClient::where('client_id', $clientId)->first();
    }

    /**
     * Validate that the redirect_uri is allowed for the given client.
     *
     * Order:
     *  1. Registered client — exact match against client.redirect_uris.
     *  2. Loopback host — accepted only outside production (AUTH-11);
     *     dev convenience for non-registered clients.
     *
     * The URL-as-client_id metadata fetch path was removed in PB-2
     * (AUTH-06: blind SSRF surface — server-side outbound HTTP to
     * attacker-chosen URL with 5s timeout, no scheme/host allowlist).
     */
    public function validateRedirectUri(string $clientId, string $redirectUri): bool
    {
        $client = $this->findClient($clientId);
        if ($client) {
            return in_array($redirectUri, $client->redirect_uris, true);
        }

        // Non-production loopback fallback for development clients
        if (! app()->environment('production')) {
            $parsed = parse_url($redirectUri);
            $host = $parsed['host'] ?? '';

            return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
        }

        return false;
    }
}
