<?php

namespace App\Http\Controllers;

use App\Services\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if (!$clientId || !$redirectUri || !$codeChallenge) {
            return redirect('/')->with('error', 'Invalid OAuth request: missing required parameters.');
        }

        if ($codeChallengeMethod !== 'S256') {
            return redirect('/')->with('error', 'Invalid OAuth request: code_challenge_method must be S256.');
        }

        if (!$this->oauthService->validateScope($scope)) {
            return redirect('/')->with('error', 'Invalid OAuth request: unsupported scope.');
        }

        if (!$this->oauthService->validateRedirectUri($clientId, $redirectUri)) {
            return redirect('/')->with('error', 'Invalid OAuth request: redirect_uri not allowed for this client.');
        }

        return view('oauth.authorize', [
            'client_id'             => $clientId,
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
            'code_challenge'        => 'required|string',
            'code_challenge_method' => 'required|in:S256',
        ]);

        if ($request->input('decision') === 'deny') {
            return redirect($request->input('redirect_uri') . '?' . http_build_query([
                'error'             => 'access_denied',
                'error_description' => 'User denied the request.',
                'state'             => $request->input('state', ''),
            ]));
        }

        $code = $this->oauthService->generateAuthorizationCode(
            user: $request->user(),
            clientId: $request->input('client_id'),
            redirectUri: $request->input('redirect_uri'),
            scope: $request->input('scope'),
            codeChallenge: $request->input('code_challenge'),
            codeChallengeMethod: $request->input('code_challenge_method'),
            resource: $request->input('resource'),
        );

        return redirect($request->input('redirect_uri') . '?' . http_build_query([
            'code'  => $code,
            'state' => $request->input('state', ''),
        ]));
    }

    public function token(Request $request): JsonResponse
    {
        $grantType = $request->input('grant_type');

        if ($grantType !== 'authorization_code') {
            return response()->json([
                'error'             => 'unsupported_grant_type',
                'error_description' => 'Only authorization_code grant is supported.',
            ], 400);
        }

        $code = $request->input('code', '');
        $codeVerifier = $request->input('code_verifier', '');
        $clientId = $request->input('client_id', '');
        $redirectUri = $request->input('redirect_uri', '');

        if (!$code || !$codeVerifier || !$clientId || !$redirectUri) {
            return response()->json([
                'error'             => 'invalid_request',
                'error_description' => 'Missing required parameters: code, code_verifier, client_id, redirect_uri.',
            ], 400);
        }

        $token = $this->oauthService->exchangeCode($code, $codeVerifier, $clientId, $redirectUri);

        if (!$token) {
            return response()->json([
                'error'             => 'invalid_grant',
                'error_description' => 'Invalid authorization code, PKCE verifier, or parameters.',
            ], 400);
        }

        return response()->json([
            'access_token' => $token->raw_token,
            'token_type'   => 'Bearer',
            'expires_in'   => config('urge.oauth.token_ttl', 3600),
            'scope'        => $token->scope,
        ]);
    }
}
