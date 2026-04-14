<?php

namespace App\Services;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Str;

class OAuthService
{
    public function generateAuthorizationCode(
        User $user,
        string $clientId,
        string $redirectUri,
        string $scope,
        string $codeChallenge,
        string $codeChallengeMethod,
        ?string $resource = null,
    ): string {
        $code = Str::random(64);

        OAuthAuthorizationCode::create([
            'code'                  => hash('sha256', $code),
            'client_id'             => $clientId,
            'user_id'               => $user->id,
            'redirect_uri'          => $redirectUri,
            'scope'                 => $scope,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
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
    ): ?OAuthAccessToken {
        $authCode = OAuthAuthorizationCode::where('code', hash('sha256', $code))
            ->where('client_id', $clientId)
            ->where('redirect_uri', $redirectUri)
            ->first();

        if (!$authCode || $authCode->isExpired()) {
            return null;
        }

        if (!$this->validatePkce($codeVerifier, $authCode->code_challenge, $authCode->code_challenge_method)) {
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

        $authCode->delete();

        $token->raw_token = $rawToken;

        return $token;
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
