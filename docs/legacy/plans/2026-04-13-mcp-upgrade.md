# MCP Server Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade URGE's MCP server to full spec compliance: Streamable HTTP transport (replacing deprecated SSE), OAuth 2.1 with PKCE (URGE-native + GitHub), discovery endpoints, scope enforcement, and backward compatibility with existing API keys.

**Architecture:** Rewrite McpController for Streamable HTTP (single endpoint, JSON or SSE response, session via header). Add OAuth 2.1 Authorization Server (OAuthService + OAuthController) with PKCE and GitHub provider. Extend DualAuthentication to triple-auth (Sanctum → OAuth → API key). Add well-known discovery endpoints. Scope enforcement in McpToolHandler.

**Tech Stack:** Laravel 12 / PHP 8.3+, SQLite, no external OAuth libraries (hand-rolled per spec)
**PHP Path:** `C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
**Spec:** `docs/superpowers/specs/2026-04-13-mcp-upgrade-design.md`

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `database/migrations/xxxx_create_oauth_tables.php` | `oauth_authorization_codes` + `oauth_access_tokens` tables |
| `app/Models/OAuthAuthorizationCode.php` | Auth code model with hashing, expiry |
| `app/Models/OAuthAccessToken.php` | Access token model with hashing, expiry, scope |
| `app/Services/OAuthService.php` | Code generation, PKCE validation, token issuance, client metadata fetch |
| `app/Http/Controllers/OAuthController.php` | `/oauth/authorize` (GET+POST), `/oauth/token` |
| `app/Http/Controllers/OAuthGitHubController.php` | GitHub OAuth flow |
| `app/Http/Controllers/WellKnownController.php` | `/.well-known/` discovery endpoints |
| `resources/views/oauth/authorize.blade.php` | Authorization consent page |
| `tests/Feature/OAuthFlowTest.php` | OAuth authorization code + PKCE tests |
| `tests/Feature/McpStreamableHttpTest.php` | Streamable HTTP transport tests |

### Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/McpController.php` | Rewrite for Streamable HTTP transport |
| `app/Http/Middleware/DualAuthentication.php` | Add OAuth token validation step |
| `app/Services/McpToolHandler.php` | Add scope checking, bump protocol version |
| `routes/web.php` | Add OAuth + well-known routes |
| `routes/api.php` | Add DELETE for MCP, remove GET /mcp |
| `config/urge.php` | Add OAuth config section |
| `.env.example` | Add GitHub + OAuth env vars |
| `tests/Feature/McpControllerTest.php` | Update for new transport + protocol version |

---

## Task 1: OAuth Database Tables + Models

**Files:**
- Create: `database/migrations/2026_04_13_000001_create_oauth_tables.php`
- Create: `app/Models/OAuthAuthorizationCode.php`
- Create: `app/Models/OAuthAccessToken.php`

- [ ] **Step 1: Create migration**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan make:migration create_oauth_tables
```

Then write the migration content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 128)->unique();
            $table->string('client_id', 2048);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('redirect_uri', 2048);
            $table->string('scope')->default('mcp:read');
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 10)->default('S256');
            $table->string('resource', 2048)->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 128)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 2048);
            $table->string('scope')->default('mcp:read');
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_authorization_codes');
    }
};
```

- [ ] **Step 2: Create OAuthAuthorizationCode model**

Create `app/Models/OAuthAuthorizationCode.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';

    protected $fillable = [
        'code', 'client_id', 'user_id', 'redirect_uri',
        'scope', 'code_challenge', 'code_challenge_method',
        'resource', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
```

- [ ] **Step 3: Create OAuthAccessToken model**

Create `app/Models/OAuthAccessToken.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthAccessToken extends Model
{
    protected $table = 'oauth_access_tokens';

    protected $fillable = [
        'token', 'user_id', 'client_id', 'scope', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        $granted = explode(' ', $this->scope);
        return in_array($scope, $granted);
    }
}
```

- [ ] **Step 4: Run migration**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan migrate
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*create_oauth_tables* app/Models/OAuthAuthorizationCode.php app/Models/OAuthAccessToken.php
git commit -m "feat: OAuth tables and models for authorization codes and access tokens"
```

---

## Task 2: OAuthService

**Files:**
- Create: `app/Services/OAuthService.php`
- Modify: `config/urge.php`
- Modify: `.env.example`

- [ ] **Step 1: Add OAuth config to urge.php**

Append to `config/urge.php` before the closing `];`:

```php
    // OAuth 2.1
    'oauth' => [
        'token_ttl'  => (int) env('OAUTH_TOKEN_TTL', 3600),
        'code_ttl'   => 600,
        'scopes'     => ['mcp:read', 'mcp:write', 'mcp:admin'],
    ],

    // GitHub OAuth (external provider)
    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
    ],
```

- [ ] **Step 2: Add env vars to .env.example**

Append to `.env.example`:

```
# OAuth 2.1 (MCP)
OAUTH_TOKEN_TTL=3600

# GitHub OAuth (optional external provider)
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

- [ ] **Step 3: Create OAuthService**

Create `app/Services/OAuthService.php`:

```php
<?php

namespace App\Services;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthorizationCode;
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

        // Validate PKCE
        if (!$this->validatePkce($codeVerifier, $authCode->code_challenge, $authCode->code_challenge_method)) {
            return null;
        }

        // Issue access token
        $rawToken = Str::random(64);
        $token = OAuthAccessToken::create([
            'token'      => hash('sha256', $rawToken),
            'user_id'    => $authCode->user_id,
            'client_id'  => $authCode->client_id,
            'scope'      => $authCode->scope,
            'expires_at' => now()->addSeconds(config('urge.oauth.token_ttl', 3600)),
        ]);

        // Delete used auth code (single use)
        $authCode->delete();

        // Return token with raw value attached for response
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

    public function validateRedirectUri(string $clientId, string $redirectUri): bool
    {
        // For URL-based client IDs, fetch metadata and check redirect_uris
        $metadata = $this->fetchClientMetadata($clientId);
        if ($metadata && isset($metadata['redirect_uris'])) {
            return in_array($redirectUri, $metadata['redirect_uris']);
        }

        // For non-URL client IDs (e.g., local apps), allow localhost/loopback redirects
        $parsed = parse_url($redirectUri);
        $host = $parsed['host'] ?? '';

        return in_array($host, ['localhost', '127.0.0.1', '[::1]']);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: All existing tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/OAuthService.php config/urge.php .env.example
git commit -m "feat: OAuthService with PKCE validation, code exchange, token issuance"
```

---

## Task 3: Well-Known Discovery Endpoints

**Files:**
- Create: `app/Http/Controllers/WellKnownController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create WellKnownController**

Create `app/Http/Controllers/WellKnownController.php`:

```php
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
        return response()->json([
            'issuer'                            => url('/'),
            'authorization_endpoint'            => url('/oauth/authorize'),
            'token_endpoint'                    => url('/oauth/token'),
            'scopes_supported'                  => config('urge.oauth.scopes', []),
            'response_types_supported'          => ['code'],
            'grant_types_supported'             => ['authorization_code'],
            'code_challenge_methods_supported'  => ['S256'],
        ]);
    }
}
```

- [ ] **Step 2: Add routes to routes/web.php**

Add before the `require __DIR__.'/auth.php';` line:

```php
// OAuth 2.1 well-known discovery (no auth required)
Route::get('/.well-known/oauth-protected-resource', [App\Http\Controllers\WellKnownController::class, 'protectedResource']);
Route::get('/.well-known/oauth-authorization-server', [App\Http\Controllers\WellKnownController::class, 'authorizationServer']);
```

- [ ] **Step 3: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/WellKnownController.php routes/web.php
git commit -m "feat: well-known OAuth discovery endpoints (RFC 9728, RFC 8414)"
```

---

## Task 4: OAuth Authorization + Token Endpoints

**Files:**
- Create: `app/Http/Controllers/OAuthController.php`
- Create: `resources/views/oauth/authorize.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create OAuthController**

Create `app/Http/Controllers/OAuthController.php`:

```php
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

    /**
     * GET /oauth/authorize — show consent page
     */
    public function showAuthorize(Request $request): View|RedirectResponse
    {
        $clientId = $request->query('client_id', '');
        $redirectUri = $request->query('redirect_uri', '');
        $scope = $request->query('scope', 'mcp:read');
        $state = $request->query('state', '');
        $codeChallenge = $request->query('code_challenge', '');
        $codeChallengeMethod = $request->query('code_challenge_method', '');
        $resource = $request->query('resource');

        // Validate required params
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

    /**
     * POST /oauth/authorize — user approves, issue code, redirect
     */
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
                'error'       => 'access_denied',
                'error_description' => 'User denied the request.',
                'state'       => $request->input('state', ''),
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

    /**
     * POST /oauth/token — exchange auth code for access token
     */
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
```

- [ ] **Step 2: Create authorize view**

Create `resources/views/oauth/authorize.blade.php`:

```blade
<x-layouts.public>
    <div class="min-h-screen flex items-center justify-center bg-gray-900 p-4">
        <div class="w-full max-w-md bg-gray-800 border border-gray-700 rounded-xl p-6">
            <h1 class="text-xl font-bold text-indigo-400 text-center mb-2">URGE</h1>
            <h2 class="text-lg font-semibold text-gray-100 text-center mb-6">Authorize Application</h2>

            <div class="bg-gray-900 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-300 mb-2">
                    <span class="text-gray-500">Application:</span>
                    <span class="font-mono text-xs break-all">{{ $client_id }}</span>
                </p>
                <p class="text-sm text-gray-300">
                    <span class="text-gray-500">Requesting access to:</span>
                </p>
                <ul class="mt-2 space-y-1">
                    @foreach ($scopes as $scope)
                        <li class="text-xs text-indigo-400 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $scope }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <form method="POST" action="{{ url('/oauth/authorize') }}">
                @csrf
                <input type="hidden" name="client_id" value="{{ $client_id }}">
                <input type="hidden" name="redirect_uri" value="{{ $redirect_uri }}">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="hidden" name="state" value="{{ $state }}">
                <input type="hidden" name="code_challenge" value="{{ $code_challenge }}">
                <input type="hidden" name="code_challenge_method" value="{{ $code_challenge_method }}">
                <input type="hidden" name="resource" value="{{ $resource }}">

                <div class="flex gap-3">
                    <button type="submit" name="decision" value="deny"
                        class="flex-1 bg-gray-700 hover:bg-gray-600 text-gray-300 py-2 rounded-lg font-medium">
                        Deny
                    </button>
                    <button type="submit" name="decision" value="approve"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium">
                        Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.public>
```

- [ ] **Step 3: Add OAuth routes**

In `routes/web.php`, add inside the `Route::middleware(['auth'])` group:

```php
    // OAuth 2.1 authorization
    Route::get('/oauth/authorize', [App\Http\Controllers\OAuthController::class, 'showAuthorize']);
    Route::post('/oauth/authorize', [App\Http\Controllers\OAuthController::class, 'handleAuthorize']);
```

Add outside any auth group (token endpoint is public):

```php
// OAuth 2.1 token exchange (no auth — client authenticates via code+PKCE)
Route::post('/oauth/token', [App\Http\Controllers\OAuthController::class, 'token']);
```

- [ ] **Step 4: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OAuthController.php resources/views/oauth/authorize.blade.php routes/web.php
git commit -m "feat: OAuth 2.1 authorization and token endpoints with PKCE"
```

---

## Task 5: GitHub OAuth Provider

**Files:**
- Create: `app/Http/Controllers/OAuthGitHubController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create OAuthGitHubController**

Create `app/Http/Controllers/OAuthGitHubController.php`:

```php
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

    /**
     * GET /oauth/github — redirect to GitHub OAuth
     */
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('urge.github.client_id');

        if (!$clientId) {
            return redirect('/')->with('error', 'GitHub OAuth not configured.');
        }

        // Store MCP OAuth params in session so we can resume after GitHub callback
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

    /**
     * GET /oauth/github/callback — handle GitHub callback
     */
    public function callback(Request $request): RedirectResponse
    {
        // Validate state
        $expectedState = $request->session()->pull('github_oauth_state');
        if (!$expectedState || $request->query('state') !== $expectedState) {
            return redirect('/')->with('error', 'Invalid GitHub OAuth state.');
        }

        $code = $request->query('code');
        if (!$code) {
            return redirect('/')->with('error', 'GitHub OAuth failed: no code returned.');
        }

        // Exchange code for GitHub access token
        $tokenResponse = Http::acceptJson()->post('https://github.com/login/oauth/access_token', [
            'client_id'     => config('urge.github.client_id'),
            'client_secret' => config('urge.github.client_secret'),
            'code'          => $code,
        ]);

        $githubToken = $tokenResponse->json('access_token');
        if (!$githubToken) {
            return redirect('/')->with('error', 'GitHub OAuth failed: could not get access token.');
        }

        // Get GitHub user info
        $githubUser = Http::withToken($githubToken)->get('https://api.github.com/user')->json();
        $email = $githubUser['email'];

        if (!$email) {
            // Fetch primary email if not public
            $emails = Http::withToken($githubToken)->get('https://api.github.com/user/emails')->json();
            $primary = collect($emails)->firstWhere('primary', true);
            $email = $primary['email'] ?? null;
        }

        if (!$email) {
            return redirect('/')->with('error', 'Could not get email from GitHub.');
        }

        // Find or create user
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name'     => $githubUser['name'] ?? $githubUser['login'],
                'email'    => $email,
                'password' => bcrypt(Str::random(32)),
            ]);
        }

        Auth::login($user);

        // Resume MCP OAuth flow if params were stored
        $oauthParams = $request->session()->pull('oauth_params');
        if ($oauthParams && !empty($oauthParams['client_id'])) {
            // Generate auth code and redirect to MCP client
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

        // No MCP flow — just redirect to app
        return redirect('/app/browse');
    }
}
```

- [ ] **Step 2: Add GitHub routes**

In `routes/web.php`, add inside the auth group (user must be logged in or will be after GitHub):

```php
    Route::get('/oauth/github', [App\Http\Controllers\OAuthGitHubController::class, 'redirect']);
    Route::get('/oauth/github/callback', [App\Http\Controllers\OAuthGitHubController::class, 'callback'])->withoutMiddleware('auth');
```

Note: The callback must be accessible without auth since the user might not be logged in yet.

- [ ] **Step 3: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/OAuthGitHubController.php routes/web.php
git commit -m "feat: GitHub OAuth provider for MCP authentication"
```

---

## Task 6: Extend DualAuthentication for OAuth Tokens

**Files:**
- Modify: `app/Http/Middleware/DualAuthentication.php`

- [ ] **Step 1: Extend middleware to triple-auth**

Rewrite `app/Http/Middleware/DualAuthentication.php`:

```php
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
```

The key distinction: URGE API keys start with `urge_` prefix, so we can quickly route to the right validator. Non-prefixed Bearer tokens try OAuth first.

- [ ] **Step 2: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: All 351+ tests pass (API key auth path unchanged).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Middleware/DualAuthentication.php
git commit -m "feat: triple-auth middleware — Sanctum, OAuth token, API key"
```

---

## Task 7: Rewrite McpController for Streamable HTTP

**Files:**
- Modify: `app/Http/Controllers/McpController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Rewrite McpController**

Replace `app/Http/Controllers/McpController.php` entirely:

```php
<?php

namespace App\Http\Controllers;

use App\Services\McpToolHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class McpController
{
    public function __construct(private McpToolHandler $handler) {}

    /**
     * POST /api/v1/mcp — Streamable HTTP transport.
     *
     * Receives JSON-RPC 2.0 requests. Returns application/json responses.
     * Assigns Mcp-Session-Id on initialize. Validates Origin header.
     */
    public function handle(Request $request): JsonResponse|Response
    {
        // Validate Origin header
        $origin = $request->header('Origin');
        if ($origin && !$this->isAllowedOrigin($origin)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32000, 'message' => 'Origin not allowed.'],
            ], 403);
        }

        // Check authentication — return 401 with discovery hint if missing
        if (!$request->user()) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32000, 'message' => 'Authentication required.'],
            ], 401)->withHeaders([
                'WWW-Authenticate' => 'Bearer resource_metadata="' . url('/.well-known/oauth-protected-resource') . '"',
            ]);
        }

        // Rate limiting: 60 requests per minute per user
        $key = 'mcp:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32000, 'message' => 'Rate limit exceeded.'],
            ], 429);
        }
        RateLimiter::hit($key, 60);

        // Validate session for non-initialize requests
        $sessionId = $request->header('Mcp-Session-Id');

        $body = $request->json()->all();

        if (!isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => $body['id'] ?? null,
                'error'   => ['code' => -32600, 'message' => 'Invalid Request: jsonrpc must be "2.0".'],
            ]);
        }

        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;

        // Notifications — acknowledge without response
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return response('', 204);
        }

        // Require session for non-initialize requests
        if ($method !== 'initialize' && $sessionId) {
            if (!Cache::has("mcp_session:{$sessionId}")) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id'      => $id,
                    'error'   => ['code' => -32000, 'message' => 'Invalid or expired session.'],
                ], 400);
            }
            // Refresh session TTL
            Cache::put("mcp_session:{$sessionId}", $request->user()->id, 3600);
        }

        $response = $this->processJsonRpc($body, $request);

        $headers = ['Content-Type' => 'application/json'];

        // On initialize, create session and attach header
        if ($method === 'initialize') {
            $newSessionId = Str::uuid()->toString();
            Cache::put("mcp_session:{$newSessionId}", $request->user()->id, 3600);
            $headers['Mcp-Session-Id'] = $newSessionId;
        }

        return response()->json($response)->withHeaders($headers);
    }

    /**
     * GET /api/v1/mcp — Server-initiated messages (not implemented).
     */
    public function stream(): Response
    {
        return response('', 405)->withHeaders([
            'Allow' => 'POST, DELETE',
        ]);
    }

    /**
     * DELETE /api/v1/mcp — Terminate session.
     */
    public function destroy(Request $request): Response
    {
        $sessionId = $request->header('Mcp-Session-Id');
        if ($sessionId) {
            Cache::forget("mcp_session:{$sessionId}");
        }

        return response('', 204);
    }

    private function processJsonRpc(array $body, Request $request): array
    {
        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;
        $params = $body['params'] ?? [];

        $result = match ($method) {
            'initialize' => [
                'protocolVersion' => '2025-06-18',
                'capabilities'    => [
                    'tools'     => ['listChanged' => false],
                    'resources' => ['subscribe' => false, 'listChanged' => false],
                ],
                'serverInfo' => $this->handler->getServerInfo(),
            ],
            'tools/list' => [
                'tools' => $this->handler->getToolDefinitions(),
            ],
            'tools/call' => $this->handleToolCall($params, $request),
            'resources/list' => [
                'resources' => $this->handler->getResourceDefinitions(),
            ],
            'resources/read' => $this->handleResourceRead($params, $request),
            'ping' => new \stdClass(),
            default => null,
        };

        if ($result === null) {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id,
                'error'   => ['code' => -32601, 'message' => "Method not found: {$method}"],
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    private function handleToolCall(array $params, Request $request): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        $user = $request->user();

        // Scope enforcement for OAuth tokens
        $oauthToken = $request->attributes->get('oauth_token');
        if ($oauthToken) {
            $requiredScope = $this->handler->getRequiredScope($toolName);
            if ($requiredScope && !$oauthToken->hasScope($requiredScope)) {
                return [
                    'content' => [
                        ['type' => 'text', 'text' => "Insufficient scope. Required: {$requiredScope}"],
                    ],
                    'isError' => true,
                ];
            }
        }

        $result = $this->handler->callTool($toolName, $arguments, $user);

        if (isset($result['error'])) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => $result['error']],
                ],
                'isError' => true,
            ];
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT)],
            ],
        ];
    }

    private function handleResourceRead(array $params, Request $request): array
    {
        $uri = $params['uri'] ?? '';
        $resource = $this->handler->readResource($uri, $request->user());

        if (isset($resource['error'])) {
            return ['contents' => []];
        }

        return ['contents' => [$resource]];
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $appUrl = config('app.url');
        if (str_starts_with($origin, $appUrl)) {
            return true;
        }
        // Allow localhost for development
        if (str_starts_with($origin, 'http://localhost') || str_starts_with($origin, 'http://127.0.0.1')) {
            return true;
        }
        return false;
    }
}
```

- [ ] **Step 2: Update MCP routes**

In `routes/api.php`, replace the MCP routes:

```php
// Before:
Route::post('mcp', [McpController::class, 'handle']);
Route::get('mcp', [McpController::class, 'stream']);

// After:
Route::post('mcp', [McpController::class, 'handle']);
Route::get('mcp', [McpController::class, 'stream']);
Route::delete('mcp', [McpController::class, 'destroy']);
```

Note: The `handle` method now handles auth internally (returns 401 with WWW-Authenticate for unauthenticated requests). The MCP routes should be moved **outside** the `dual.auth` middleware group so the 401 discovery flow works. Move them to their own group:

```php
// MCP (auth handled internally for OAuth discovery)
Route::post('mcp', [McpController::class, 'handle'])->middleware('dual.auth');
Route::get('mcp', [McpController::class, 'stream']);
Route::delete('mcp', [McpController::class, 'destroy']);
```

Actually, simpler approach: keep them in `dual.auth` but make the middleware pass through unauthenticated MCP requests (the controller handles 401 itself). OR move them out and let the controller handle all auth. Let's keep them in `dual.auth` — the middleware will still try all 3 auth methods, and if none match, the API key middleware returns 401. We need to customize the 401 response. Better approach: move MCP routes out of `dual.auth`, let McpController handle auth:

In `routes/api.php`, move MCP routes outside the `dual.auth` group:

```php
    // MCP — auth handled internally for OAuth 2.1 discovery flow
    Route::post('mcp', [McpController::class, 'handle']);
    Route::get('mcp', [McpController::class, 'stream']);
    Route::delete('mcp', [McpController::class, 'destroy']);
```

Then update McpController::handle to do its own auth check:

Add at the start of the `handle` method, after Origin validation but before rate limiting, apply the dual auth manually:

```php
// Try to authenticate (Sanctum → OAuth → API key)
app(\App\Http\Middleware\DualAuthentication::class)->handle($request, function ($req) {}, );
```

Actually, the cleanest approach: keep MCP routes in `dual.auth` group but add a **new middleware** that customizes the 401 response for MCP. Simplest: just override the 401 response. The current `ApiKeyAuthentication` middleware returns `abort(401)` — we need to catch that and add the `WWW-Authenticate` header.

**Simplest approach:** Keep MCP in `dual.auth`. Add the `WWW-Authenticate` header handling in the McpController by wrapping with a try/catch or by checking `$request->user()` after middleware runs. But `dual.auth` will abort before reaching the controller.

**Final decision:** Move MCP POST out of `dual.auth`. Apply `dual.auth` as an **optional** middleware that doesn't abort — or just apply auth manually in the controller. The controller already checks `$request->user()` and returns the proper 401. So: move MCP routes out of `dual.auth`, add manual auth resolution in the controller.

Update McpController::handle, after Origin validation, add:

```php
// Attempt authentication (same logic as DualAuthentication middleware, but non-aborting)
if (!$request->user()) {
    $bearer = $request->bearerToken();
    if ($bearer) {
        if (!str_starts_with($bearer, config('urge.key_prefix', 'urge_'))) {
            $oauthToken = app(\App\Services\OAuthService::class)->findByToken($bearer);
            if ($oauthToken) {
                $request->setUserResolver(fn () => $oauthToken->user);
                $request->attributes->set('oauth_token', $oauthToken);
            }
        } else {
            $apiKey = app(\App\Services\ApiKeyService::class)->findByToken($bearer);
            if ($apiKey && $apiKey->is_active) {
                $apiKey->update(['last_used_at' => now()]);
                $request->setUserResolver(fn () => $apiKey->user);
                $request->attributes->set('api_key', $apiKey);
            }
        }
    }
}
```

This replaces the middleware-based auth for the MCP endpoint specifically, allowing the controller to return the proper OAuth 401 response.

- [ ] **Step 3: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/McpController.php routes/api.php
git commit -m "feat: Streamable HTTP transport with session management and OAuth 401 discovery"
```

---

## Task 8: Scope Enforcement in McpToolHandler

**Files:**
- Modify: `app/Services/McpToolHandler.php`

- [ ] **Step 1: Add getRequiredScope method**

Add to `app/Services/McpToolHandler.php` after `getServerInfo()`:

```php
    public function getRequiredScope(string $toolName): ?string
    {
        $readTools = [
            'get_prompt', 'list_prompts', 'render_prompt',
            'get_results', 'list_branches', 'list_teams', 'list_templates',
        ];

        $writeTools = [
            'save_version', 'store_result', 'update_result',
            'create_branch', 'share_prompt', 'run_template',
        ];

        $adminTools = [
            'delete_prompt', 'delete_result',
        ];

        if (in_array($toolName, $readTools)) {
            return 'mcp:read';
        }
        if (in_array($toolName, $writeTools)) {
            return 'mcp:write';
        }
        if (in_array($toolName, $adminTools)) {
            return 'mcp:admin';
        }

        return null;
    }
```

- [ ] **Step 2: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/McpToolHandler.php
git commit -m "feat: scope enforcement for OAuth tokens in MCP tool handler"
```

---

## Task 9: OAuth Flow Test

**Files:**
- Create: `tests/Feature/OAuthFlowTest.php`

- [ ] **Step 1: Create OAuth test**

Create `tests/Feature/OAuthFlowTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'OAuth User',
            'email' => 'oauth@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_well_known_protected_resource(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $response->assertOk()
            ->assertJsonStructure(['resource', 'authorization_servers', 'scopes_supported'])
            ->assertJsonPath('scopes_supported', ['mcp:read', 'mcp:write', 'mcp:admin']);
    }

    public function test_well_known_authorization_server(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertOk()
            ->assertJsonStructure([
                'issuer', 'authorization_endpoint', 'token_endpoint',
                'scopes_supported', 'code_challenge_methods_supported',
            ])
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_authorize_shows_consent_page(): void
    {
        $this->actingAs($this->user);

        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->get('/oauth/authorize?' . http_build_query([
            'client_id'             => 'https://example.com/client',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:write',
            'state'                 => 'test-state',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $response->assertOk()
            ->assertSee('Authorize Application')
            ->assertSee('mcp:write');
    }

    public function test_full_oauth_flow_with_pkce(): void
    {
        $this->actingAs($this->user);

        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Step 1: Approve authorization
        $response = $this->post('/oauth/authorize', [
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:write',
            'state'                 => 'test-state',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'decision'              => 'approve',
        ]);

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('code', $query);
        $this->assertEquals('test-state', $query['state']);

        // Step 2: Exchange code for token
        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $query['code'],
            'code_verifier' => $verifier,
            'client_id'     => 'http://localhost:3000',
            'redirect_uri'  => 'http://localhost:3000/callback',
        ]);

        $tokenResponse->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'scope'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('scope', 'mcp:write');

        $accessToken = $tokenResponse->json('access_token');

        // Step 3: Use token on MCP endpoint
        $mcpResponse = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id'      => '1',
            'method'  => 'initialize',
            'params'  => [],
        ], ['Authorization' => "Bearer {$accessToken}"]);

        $mcpResponse->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_pkce_invalid_verifier_rejected(): void
    {
        $this->actingAs($this->user);

        $verifier = 'correct-verifier-string-for-challenge';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->post('/oauth/authorize', [
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:read',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'decision'              => 'approve',
        ]);

        $redirectUrl = $response->headers->get('Location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);

        // Exchange with WRONG verifier
        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $query['code'],
            'code_verifier' => 'wrong-verifier-should-fail',
            'client_id'     => 'http://localhost:3000',
            'redirect_uri'  => 'http://localhost:3000/callback',
        ]);

        $tokenResponse->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_deny_authorization(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/oauth/authorize', [
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:read',
            'code_challenge'        => 'some-challenge',
            'code_challenge_method' => 'S256',
            'decision'              => 'deny',
        ]);

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('error=access_denied', $redirectUrl);
    }

    public function test_mcp_unauthenticated_returns_401_with_discovery(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id'      => '1',
            'method'  => 'initialize',
            'params'  => [],
        ]);

        $response->assertStatus(401)
            ->assertHeader('WWW-Authenticate');

        $wwwAuth = $response->headers->get('WWW-Authenticate');
        $this->assertStringContainsString('resource_metadata=', $wwwAuth);
    }

    public function test_scope_enforcement_blocks_write_with_read_token(): void
    {
        $oauthService = app(OAuthService::class);

        // Create a read-only token directly
        $verifier = 'test-scope-verifier-string';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $code = $oauthService->generateAuthorizationCode(
            user: $this->user,
            clientId: 'http://localhost:3000',
            redirectUri: 'http://localhost:3000/callback',
            scope: 'mcp:read',
            codeChallenge: $challenge,
            codeChallengeMethod: 'S256',
        );

        $token = $oauthService->exchangeCode($code, $verifier, 'http://localhost:3000', 'http://localhost:3000/callback');

        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id'      => '1',
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'delete_prompt',
                'arguments' => ['slug' => 'test'],
            ],
        ], ['Authorization' => "Bearer {$token->raw_token}"]);

        $response->assertOk();
        $content = $response->json('result.content.0.text');
        $this->assertStringContainsString('Insufficient scope', $content);
    }
}
```

- [ ] **Step 2: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/OAuthFlowTest.php
git commit -m "test: OAuth flow tests — full PKCE flow, scope enforcement, 401 discovery"
```

---

## Task 10: Update Existing MCP Tests

**Files:**
- Modify: `tests/Feature/McpControllerTest.php`

- [ ] **Step 1: Update tests for new transport**

Key changes to `tests/Feature/McpControllerTest.php`:

1. Update `test_initialize` to assert `protocolVersion` = `2025-06-18`
2. Remove all SSE transport tests (cache-based queueing is gone):
   - `test_sse_post_with_session_id_returns_202_and_queues_response`
   - `test_sse_tools_list_queued`
   - `test_sse_notification_returns_202_without_queueing`
   - `test_sse_multiple_messages_queued_sequentially`
   - `test_sse_stream_returns_event_stream_content_type`
3. Add `test_initialize_returns_session_id_header` — check `Mcp-Session-Id` header
4. Add `test_delete_terminates_session` — send DELETE with session header
5. Update `test_requires_authentication` to check for `WWW-Authenticate` header
6. Add `test_get_returns_405` — GET /mcp returns 405

The full updated test file should replace the SSE tests with Streamable HTTP tests. Keep all direct transport tests (they still work — just with updated protocol version assertion).

- [ ] **Step 2: Run tests**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/McpControllerTest.php
git commit -m "test: update MCP tests for Streamable HTTP transport"
```

---

## Task 11: Integration + Verify

- [ ] **Step 1: Build frontend**

```bash
npm run build
```

- [ ] **Step 2: Run full test suite**

```bash
"C:/SAP/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan test
```

Expected: All tests pass (351+ existing + new OAuth + updated MCP).

- [ ] **Step 3: Manual verification**

1. `GET /.well-known/oauth-protected-resource` → valid JSON
2. `GET /.well-known/oauth-authorization-server` → valid JSON
3. `POST /api/v1/mcp` without auth → 401 with `WWW-Authenticate` header
4. `POST /api/v1/mcp` with existing API key → works (backward compat)
5. OAuth flow: authorize → code → token → MCP call works
6. PKCE: wrong verifier → token exchange rejected
7. Scope: read token → write tool rejected
8. Session: `Mcp-Session-Id` returned on initialize
9. `DELETE /api/v1/mcp` with session ID → 204
10. `GET /api/v1/mcp` → 405
11. `php artisan urge:mcp-server` → stdio still works

- [ ] **Step 4: Commit and push**

```bash
git add -A
git commit -m "feat: MCP upgrade complete — Streamable HTTP, OAuth 2.1, scope enforcement"
git push
```

---

## Verification Summary

| Feature | How to verify |
|---------|--------------|
| Streamable HTTP | POST /mcp returns JSON, no SSE polling |
| Session management | Initialize returns Mcp-Session-Id header |
| Session termination | DELETE /mcp clears session |
| Protocol version | Initialize returns 2025-06-18 |
| OAuth discovery (401) | Unauthenticated POST → 401 + WWW-Authenticate |
| Protected Resource Metadata | GET /.well-known/oauth-protected-resource |
| Auth Server Metadata | GET /.well-known/oauth-authorization-server |
| OAuth consent page | GET /oauth/authorize shows approval form |
| PKCE flow | Code + verifier → token |
| PKCE rejection | Wrong verifier → 400 invalid_grant |
| Token → MCP | OAuth Bearer token authenticates MCP calls |
| Scope enforcement | Read token blocked from admin tools |
| API key backward compat | urge_ Bearer tokens still work |
| Sanctum backward compat | SPA session auth still works |
| GitHub OAuth | /oauth/github → GitHub → callback → token |
| stdio transport | urge:mcp-server unchanged |
| Origin validation | Unknown Origin → 403 |
