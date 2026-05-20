<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\OAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthGitHubController
{
    public function __construct(private OAuthService $oauthService) {}

    /**
     * GET /oauth/github
     *
     * Pre-validates the inner URGE OAuth request (client_id /
     * redirect_uri / scope / PKCE) BEFORE redirecting the user out to
     * GitHub, then stores the validated values in the session. This
     * closes AUTH-01 for the GitHub flow: the callback never trusts
     * raw URLs from the original query string.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('urge.github.client_id');

        if (!$clientId) {
            return redirect('/')->with('error', 'GitHub OAuth not configured.');
        }

        $urgeClientId = $request->query('client_id', '');
        $urgeRedirectUri = $request->query('redirect_uri', '');
        $scope = $request->query('scope', 'mcp:read');
        $codeChallenge = $request->query('code_challenge', '');
        $codeChallengeMethod = $request->query('code_challenge_method', '');

        // Validate before persisting attacker-controlled URIs in session
        if (!$urgeClientId || !$urgeRedirectUri) {
            return redirect('/')->with('error', 'Missing OAuth parameters.');
        }
        if (!$this->oauthService->validateScope($scope)) {
            return redirect('/')->with('error', 'Unsupported scope.');
        }
        if (!$this->oauthService->validateRedirectUri($urgeClientId, $urgeRedirectUri)) {
            return redirect('/')->with('error', 'redirect_uri not allowed for this client.');
        }

        $client = $this->oauthService->findClient($urgeClientId);
        $isConfidential = $client && $client->client_secret;
        if (!$isConfidential && !$codeChallenge) {
            return redirect('/')->with('error', 'code_challenge is required for public clients.');
        }
        if ($codeChallenge && $codeChallengeMethod !== 'S256') {
            return redirect('/')->with('error', 'code_challenge_method must be S256.');
        }

        $request->session()->put('oauth_params', [
            'client_id'             => $urgeClientId,
            'redirect_uri'          => $urgeRedirectUri,
            'scope'                 => $scope,
            'state'                 => $request->query('state', ''),
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'resource'              => $request->query('resource'),
        ]);

        $githubState = Str::random(40);
        $request->session()->put('github_oauth_state', $githubState);

        $query = http_build_query([
            'client_id'    => $clientId,
            'redirect_uri' => url('/oauth/github/callback'),
            'scope'        => 'user:email',
            'state'        => $githubState,
        ]);

        return redirect("https://github.com/login/oauth/authorize?{$query}");
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('github_oauth_state');
        if (!$expectedState || $request->query('state') !== $expectedState) {
            return redirect('/')->with('error', 'Invalid GitHub OAuth state.');
        }

        $code = $request->query('code');
        if (!$code) {
            return redirect('/')->with('error', 'GitHub OAuth failed: no code returned.');
        }

        // Exchange the code with GitHub for an access token (with error
        // handling — AUTH-04).
        try {
            $tokenResponse = Http::acceptJson()->post('https://github.com/login/oauth/access_token', [
                'client_id'     => config('urge.github.client_id'),
                'client_secret' => config('urge.github.client_secret'),
                'code'          => $code,
            ]);
        } catch (\Throwable $e) {
            return redirect('/')->with('error', 'GitHub OAuth failed: token request errored.');
        }

        if (!$tokenResponse->successful()) {
            return redirect('/')->with('error', 'GitHub OAuth failed: token request rejected.');
        }
        $githubToken = $tokenResponse->json('access_token');
        if (!$githubToken || !is_string($githubToken)) {
            return redirect('/')->with('error', 'GitHub OAuth failed: no access token.');
        }

        // Fetch profile
        $githubUser = $this->fetchGithubJson($githubToken, 'https://api.github.com/user');
        if (!$githubUser || empty($githubUser['id'])) {
            return redirect('/')->with('error', 'Could not fetch GitHub profile.');
        }

        // Look up identity first (provider+sub). Email is informational.
        $providerUserId = (string) $githubUser['id'];
        $identity = UserIdentity::where('provider', 'github')
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($identity) {
            // Existing federated account — trust the binding regardless
            // of any email churn upstream.
            Auth::login($identity->user);
            return $this->resumeAuthorize($request, $identity->user);
        }

        // New federated account. Require a VERIFIED email (AUTH-03).
        // /user.email may be null if the user has it private; fall back
        // to /user/emails and pick the first primary+verified entry.
        [$email, $verified] = $this->resolvePrimaryVerifiedEmail($githubToken, $githubUser);

        if (!$email || !$verified) {
            return redirect('/')->with('error', 'A verified primary email on your GitHub account is required.');
        }

        // Refuse to silently link to an existing local account by email —
        // that's the pre-AUTH-03 ATO path. The local user must
        // explicitly link from their settings (out of PB-2 scope).
        if (User::where('email', $email)->exists()) {
            return redirect('/')->with(
                'error',
                'An account already exists for this email. Log in with your password and link GitHub from settings.'
            );
        }

        $name = $githubUser['name'] ?? $githubUser['login'] ?? 'GitHub User';

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => bcrypt(Str::random(32)),
        ]);

        UserIdentity::create([
            'user_id'          => $user->id,
            'provider'         => 'github',
            'provider_user_id' => $providerUserId,
            'email'            => $email,
            'email_verified'   => true,
        ]);

        Auth::login($user);

        return $this->resumeAuthorize($request, $user);
    }

    private function fetchGithubJson(string $githubToken, string $url): ?array
    {
        try {
            $resp = Http::withToken($githubToken)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$resp->successful()) {
            return null;
        }

        $data = $resp->json();

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{0: ?string, 1: bool}  [email, verified]
     */
    private function resolvePrimaryVerifiedEmail(string $githubToken, array $githubUser): array
    {
        // First try the profile's `email`; GitHub only surfaces it here
        // if it's verified AND not marked private — but we can't be
        // certain it's verified from the /user endpoint alone, so we
        // re-confirm via /user/emails when possible.
        $emails = $this->fetchGithubJson($githubToken, 'https://api.github.com/user/emails');

        if (is_array($emails)) {
            foreach ($emails as $entry) {
                if (
                    is_array($entry)
                    && !empty($entry['primary'])
                    && !empty($entry['verified'])
                    && !empty($entry['email'])
                    && is_string($entry['email'])
                ) {
                    return [$entry['email'], true];
                }
            }
        }

        // No verified primary email
        return [null, false];
    }

    private function resumeAuthorize(Request $request, User $user): RedirectResponse
    {
        $oauthParams = $request->session()->pull('oauth_params');
        if (!$oauthParams || empty($oauthParams['client_id'])) {
            return redirect('/app/browse');
        }

        // Re-validate the (session-stored, originally validated) URI
        // defensively — covers cookie-tampered sessions.
        if (!$this->oauthService->validateRedirectUri($oauthParams['client_id'], $oauthParams['redirect_uri'])) {
            return redirect('/')->with('error', 'redirect_uri is no longer valid for this client.');
        }

        $urgeCode = $this->oauthService->generateAuthorizationCode(
            user: $user,
            clientId: $oauthParams['client_id'],
            redirectUri: $oauthParams['redirect_uri'],
            scope: $oauthParams['scope'] ?? 'mcp:read',
            codeChallenge: $oauthParams['code_challenge'] ?? null,
            codeChallengeMethod: $oauthParams['code_challenge_method'] ?? null,
            resource: $oauthParams['resource'] ?? null,
        );

        return redirect($oauthParams['redirect_uri'] . '?' . http_build_query([
            'code'  => $urgeCode,
            'state' => $oauthParams['state'] ?? '',
        ]));
    }
}
