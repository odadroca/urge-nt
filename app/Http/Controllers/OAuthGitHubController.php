<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthGitHubController
{
    public function __construct(private OAuthService $oauthService) {}

    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('urge.github.client_id');

        if (!$clientId) {
            return redirect('/')->with('error', 'GitHub OAuth not configured.');
        }

        $request->session()->put('oauth_params', $request->only([
            'client_id', 'redirect_uri', 'scope', 'state',
            'code_challenge', 'code_challenge_method', 'resource',
        ]));

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

        $tokenResponse = Http::acceptJson()->post('https://github.com/login/oauth/access_token', [
            'client_id'     => config('urge.github.client_id'),
            'client_secret' => config('urge.github.client_secret'),
            'code'          => $code,
        ]);

        $githubToken = $tokenResponse->json('access_token');
        if (!$githubToken) {
            return redirect('/')->with('error', 'GitHub OAuth failed: could not get access token.');
        }

        $githubUser = Http::withToken($githubToken)->get('https://api.github.com/user')->json();
        $email = $githubUser['email'];

        if (!$email) {
            $emails = Http::withToken($githubToken)->get('https://api.github.com/user/emails')->json();
            $primary = collect($emails)->firstWhere('primary', true);
            $email = $primary['email'] ?? null;
        }

        if (!$email) {
            return redirect('/')->with('error', 'Could not get email from GitHub.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name'     => $githubUser['name'] ?? $githubUser['login'],
                'email'    => $email,
                'password' => bcrypt(Str::random(32)),
            ]);
        }

        Auth::login($user);

        $oauthParams = $request->session()->pull('oauth_params');
        if ($oauthParams && !empty($oauthParams['client_id'])) {
            $urgeCode = $this->oauthService->generateAuthorizationCode(
                user: $user,
                clientId: $oauthParams['client_id'],
                redirectUri: $oauthParams['redirect_uri'],
                scope: $oauthParams['scope'] ?? 'mcp:read',
                codeChallenge: $oauthParams['code_challenge'],
                codeChallengeMethod: $oauthParams['code_challenge_method'],
                resource: $oauthParams['resource'] ?? null,
            );

            return redirect($oauthParams['redirect_uri'] . '?' . http_build_query([
                'code'  => $urgeCode,
                'state' => $oauthParams['state'] ?? '',
            ]));
        }

        return redirect('/app/browse');
    }
}
