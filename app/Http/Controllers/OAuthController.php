<?php

namespace App\Http\Controllers;

use App\Models\OAuthClient;
use App\Services\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OAuthController
{
    public function __construct(private OAuthService $oauthService) {}

    public function showAuthorize(Request $request): View|RedirectResponse
    {
        $clientId = $request->query('client_id', '');
        $redirectUri = $request->query('redirect_uri', '');
        $scope = $request->query('scope', 'mcp:read');
        $state = $request->query('state', '');
        $codeChallenge = $request->query('code_challenge', '');
        $codeChallengeMethod = $request->query('code_challenge_method', '');
        $resource = $request->query('resource');

        if (!$clientId || !$redirectUri) {
            return redirect('/')->with('error', 'Invalid OAuth request: missing required parameters.');
        }

        // PKCE is required for public clients, optional for confidential clients
        $client = $this->oauthService->findClient($clientId);
        $isConfidential = $client && $client->client_secret;

        if (!$isConfidential && !$codeChallenge) {
            return redirect('/')->with('error', 'Invalid OAuth request: code_challenge is required for public clients.');
        }

        if ($codeChallenge && $codeChallengeMethod !== 'S256') {
            return redirect('/')->with('error', 'Invalid OAuth request: code_challenge_method must be S256.');
        }

        if (!$this->oauthService->validateScope($scope)) {
            return redirect('/')->with('error', 'Invalid OAuth request: unsupported scope.');
        }

        if (!$this->oauthService->validateRedirectUri($clientId, $redirectUri)) {
            return redirect('/')->with('error', 'Invalid OAuth request: redirect_uri not allowed for this client.');
        }

        $clientName = null;
        $client = $this->oauthService->findClient($clientId);
        if ($client) {
            $clientName = $client->client_name;
        }

        return view('oauth.authorize', [
            'client_id'             => $clientId,
            'client_name'           => $clientName,
            'redirect_uri'          => $redirectUri,
            'scope'                 => $scope,
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'resource'              => $resource,
            'scopes'                => explode(' ', $scope),
        ]);
    }

    public function handleAuthorize(Request $request): RedirectResponse
    {
        $request->validate([
            'client_id'             => 'required|string',
            'redirect_uri'          => 'required|string|url',
            'scope'                 => 'required|string',
            'code_challenge'        => 'nullable|string',
            'code_challenge_method' => 'nullable|in:S256',
        ]);

        $clientId = $request->input('client_id');
        $redirectUri = $request->input('redirect_uri');
        $state = $request->input('state', '');

        // Re-validate the redirect_uri allowlist on this code-issuing path.
        // Without this, the GET allowlist check is bypassable by a forged
        // POST that goes straight to consent submission (AUTH-01).
        if (!$this->oauthService->validateRedirectUri($clientId, $redirectUri)) {
            return redirect('/')->with('error', 'Invalid OAuth request: redirect_uri not allowed for this client.');
        }

        if ($request->input('decision') === 'deny') {
            return redirect($this->buildRedirectUrl($redirectUri, [
                'error'             => 'access_denied',
                'error_description' => 'User denied the request.',
                'state'             => $state,
            ]));
        }

        $code = $this->oauthService->generateAuthorizationCode(
            user: $request->user(),
            clientId: $clientId,
            redirectUri: $redirectUri,
            scope: $request->input('scope'),
            codeChallenge: $request->input('code_challenge'),
            codeChallengeMethod: $request->input('code_challenge_method'),
            resource: $request->input('resource'),
        );

        return redirect($this->buildRedirectUrl($redirectUri, [
            'code'  => $code,
            'state' => $state,
        ]));
    }

    public function token(Request $request): JsonResponse
    {
        $grantType = $request->input('grant_type');

        return match ($grantType) {
            'authorization_code' => $this->handleAuthorizationCodeGrant($request),
            'refresh_token'      => $this->handleRefreshTokenGrant($request),
            default              => response()->json([
                'error'             => 'unsupported_grant_type',
                'error_description' => 'Supported grant types: authorization_code, refresh_token.',
            ], 400),
        };
    }

    private function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $code = $request->input('code', '');
        $codeVerifier = $request->input('code_verifier', '');
        $clientId = $request->input('client_id', '');
        $clientSecret = $request->input('client_secret', '');
        $redirectUri = $request->input('redirect_uri', '');

        if (!$code || !$clientId || !$redirectUri) {
            return response()->json([
                'error'             => 'invalid_request',
                'error_description' => 'Missing required parameters: code, client_id, redirect_uri.',
            ], 400);
        }

        if (!$codeVerifier && !$clientSecret) {
            return response()->json([
                'error'             => 'invalid_request',
                'error_description' => 'Either code_verifier (PKCE) or client_secret is required.',
            ], 400);
        }

        $token = $this->oauthService->exchangeCode($code, $codeVerifier, $clientId, $redirectUri, $clientSecret);

        if (!$token) {
            return response()->json([
                'error'             => 'invalid_grant',
                'error_description' => 'Invalid authorization code, PKCE verifier, or parameters.',
            ], 400);
        }

        return $this->tokenResponse($token);
    }

    private function handleRefreshTokenGrant(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token', '');
        $clientId = $request->input('client_id', '');
        $scope = $request->input('scope');

        if (!$refreshToken || !$clientId) {
            return response()->json([
                'error'             => 'invalid_request',
                'error_description' => 'Missing required parameters: refresh_token, client_id.',
            ], 400);
        }

        $token = $this->oauthService->refreshToken($refreshToken, $clientId, $scope);

        if (!$token) {
            return response()->json([
                'error'             => 'invalid_grant',
                'error_description' => 'Invalid or expired refresh token.',
            ], 400);
        }

        return $this->tokenResponse($token);
    }

    private function tokenResponse(mixed $token): JsonResponse
    {
        return response()->json([
            'access_token'  => $token->raw_token,
            'token_type'    => 'Bearer',
            'expires_in'    => config('urge.oauth.token_ttl', 3600),
            'refresh_token' => $token->raw_refresh_token,
            'scope'         => $token->scope,
        ]);
    }

    /**
     * POST /oauth/register — Dynamic Client Registration (RFC 7591)
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->json()->all();

        // redirect_uris is required
        if (empty($data['redirect_uris']) || !is_array($data['redirect_uris'])) {
            return response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => 'redirect_uris is required and must be a non-empty array.',
            ], 400);
        }

        // Validate each redirect URI
        foreach ($data['redirect_uris'] as $uri) {
            $parsed = parse_url($uri);
            $scheme = $parsed['scheme'] ?? '';
            $host = $parsed['host'] ?? '';

            if ($scheme === 'http') {
                // HTTP only allowed for loopback
                if (!in_array($host, ['localhost', '127.0.0.1', '[::1]'])) {
                    return response()->json([
                        'error' => 'invalid_client_metadata',
                        'error_description' => "HTTP redirect URIs are only allowed for loopback addresses. Got: {$uri}",
                    ], 400);
                }
            } elseif ($scheme !== 'https') {
                return response()->json([
                    'error' => 'invalid_client_metadata',
                    'error_description' => "Redirect URIs must use https:// (or http:// for loopback). Got: {$uri}",
                ], 400);
            }
        }

        $clientId = Str::uuid()->toString();
        $authMethod = $data['token_endpoint_auth_method'] ?? 'none';
        $rawSecret = null;

        // Generate client_secret for confidential clients
        if ($authMethod === 'client_secret_post' || $authMethod === 'client_secret_basic') {
            $rawSecret = Str::random(48);
        }

        $client = OAuthClient::create([
            'client_id'                   => $clientId,
            'client_secret'               => $rawSecret ? hash('sha256', $rawSecret) : null,
            'client_name'                 => $data['client_name'] ?? null,
            'redirect_uris'               => $data['redirect_uris'],
            'grant_types'                 => $data['grant_types'] ?? ['authorization_code'],
            'response_types'              => $data['response_types'] ?? ['code'],
            'token_endpoint_auth_method'  => $authMethod,
            'scope'                       => $data['scope'] ?? null,
        ]);

        $response = [
            'client_id'                   => $client->client_id,
            'client_name'                 => $client->client_name,
            'redirect_uris'               => $client->redirect_uris,
            'client_id_issued_at'         => $client->created_at->timestamp,
            'client_secret_expires_at'    => 0,
            'grant_types'                 => $client->grant_types,
            'response_types'              => $client->response_types,
            'token_endpoint_auth_method'  => $client->token_endpoint_auth_method,
        ];

        if ($rawSecret) {
            $response['client_secret'] = $rawSecret;
        }

        return response()->json($response, 201);
    }

    /**
     * RFC 7009 token revocation. Always responds 200 (per spec)
     * regardless of whether the token existed. Confidential clients
     * authenticate with client_secret; public clients identify themselves
     * by client_id only (the token itself is the proof of possession).
     */
    public function revoke(Request $request): JsonResponse
    {
        $token = $request->input('token', '');
        $clientId = $request->input('client_id', '');
        $clientSecret = $request->input('client_secret', '');

        if (!$token || !$clientId) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'token and client_id are required.',
            ], 400);
        }

        $client = $this->oauthService->findClient($clientId);
        if ($client && $client->client_secret) {
            if (!hash_equals($client->client_secret, hash('sha256', $clientSecret))) {
                return response()->json([
                    'error' => 'invalid_client',
                    'error_description' => 'Client authentication failed.',
                ], 401);
            }
        }

        $this->oauthService->revokeToken($token, $clientId);

        // RFC 7009 §2.2: always 200, even when the token was not found
        return response()->json(new \stdClass(), 200);
    }

    private function buildRedirectUrl(string $baseUri, array $params): string
    {
        $separator = str_contains($baseUri, '?') ? '&' : '?';

        return $baseUri . $separator . http_build_query($params);
    }
}
