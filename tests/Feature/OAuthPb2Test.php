<?php

namespace Tests\Feature;

use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\OAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Phase B Sprint 2 regression tests — closes the OAuth & GitHub
 * audit findings (AUTH-01..11).
 */
class OAuthPb2Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private string $challenge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'OAuth User',
            'email' => 'oauth@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->challenge = rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');

        // Clear rate-limiters between tests (we exercise them deliberately)
        RateLimiter::clear($this->throttleKey('oauth/token'));
        RateLimiter::clear($this->throttleKey('oauth/register'));
        RateLimiter::clear($this->throttleKey('oauth/revoke'));
    }

    private function throttleKey(string $path): string
    {
        // Laravel's throttle middleware keys per IP for unauthenticated
        // routes; tests run from 127.0.0.1. We don't rely on the exact key
        // — RateLimiter::clear() with the resolved key handles it. This
        // helper exists so tests stay readable.
        return sha1("{$path}|127.0.0.1");
    }

    // ---------- AUTH-01: redirect_uri allowlist on consent POST ----------

    public function test_consent_post_rejects_unregistered_redirect_uri(): void
    {
        // Register a confidential client with a specific redirect URI
        $resp = $this->actingAs($this->user)->postJson('/oauth/register', [
            'redirect_uris' => ['https://app.example.com/callback'],
            'token_endpoint_auth_method' => 'none',
        ]);
        $clientId = $resp->json('client_id');

        $response = $this->actingAs($this->user)->post('/oauth/authorize', [
            'client_id' => $clientId,
            'redirect_uri' => 'https://evil.example.com/exfil', // not on allowlist
            'scope' => 'mcp:read',
            'code_challenge' => $this->challenge,
            'code_challenge_method' => 'S256',
            'decision' => 'allow',
        ]);

        $response->assertRedirect('/');
        $this->assertEquals(0, OAuthAuthorizationCode::count());
    }

    // ---------- AUTH-02: throttling on /oauth/token and /oauth/register ----------

    public function test_oauth_token_endpoint_is_throttled(): void
    {
        // Throttle limit is 20/min. Fire 21 invalid requests; the 21st
        // should be rate-limited (429), not just invalid (400).
        $last = null;
        for ($i = 0; $i < 21; $i++) {
            $last = $this->postJson('/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => 'nonexistent',
                'client_id' => 'http://localhost:3000',
                'redirect_uri' => 'http://localhost:3000/callback',
            ]);
        }
        $this->assertEquals(429, $last->status());
    }

    public function test_oauth_register_endpoint_is_throttled(): void
    {
        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->postJson('/oauth/register', [
                'redirect_uris' => ["http://localhost:300{$i}/callback"],
            ]);
        }
        $this->assertEquals(429, $last->status());
    }

    // ---------- AUTH-03: verified-email + provider-identity ----------

    public function test_github_callback_refuses_unverified_email(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test'], 200),
            'https://api.github.com/user' => Http::response(['id' => 99999, 'login' => 'unverified', 'name' => 'U'], 200),
            'https://api.github.com/user/emails' => Http::response([
                ['email' => 'u@example.com', 'primary' => true, 'verified' => false],
            ], 200),
        ]);

        // Prime session with valid state
        $state = 'test-state-xyz';
        session(['github_oauth_state' => $state]);

        $response = $this->get("/oauth/github/callback?code=abc&state={$state}");

        $response->assertRedirect('/');
        $this->assertDatabaseMissing('user_identities', ['provider_user_id' => '99999']);
        $this->assertDatabaseMissing('users', ['email' => 'u@example.com']);
    }

    public function test_github_callback_refuses_silent_link_to_existing_local_account(): void
    {
        // Pre-existing local account with the email
        $local = User::create([
            'name' => 'Local',
            'email' => 'collide@example.com',
            'password' => bcrypt('whatever'),
        ]);

        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test'], 200),
            'https://api.github.com/user' => Http::response(['id' => 12345, 'login' => 'collide'], 200),
            'https://api.github.com/user/emails' => Http::response([
                ['email' => 'collide@example.com', 'primary' => true, 'verified' => true],
            ], 200),
        ]);

        $state = 'state-collision';
        session(['github_oauth_state' => $state]);

        $response = $this->get("/oauth/github/callback?code=abc&state={$state}");

        $response->assertRedirect('/');
        // No identity created, and the local user remains un-federated
        $this->assertDatabaseMissing('user_identities', ['user_id' => $local->id]);
        // No new user created with that email either
        $this->assertEquals(1, User::where('email', 'collide@example.com')->count());
    }

    public function test_github_callback_creates_identity_for_new_verified_account(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test'], 200),
            'https://api.github.com/user' => Http::response(['id' => 55555, 'login' => 'newgh', 'name' => 'New GH'], 200),
            'https://api.github.com/user/emails' => Http::response([
                ['email' => 'new@example.com', 'primary' => true, 'verified' => true],
            ], 200),
        ]);

        $state = 'state-new';
        session(['github_oauth_state' => $state]);

        $this->get("/oauth/github/callback?code=abc&state={$state}");

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '55555',
            'email_verified' => true,
        ]);
    }

    public function test_github_callback_reuses_existing_identity(): void
    {
        $existing = User::create([
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password' => bcrypt('whatever'),
        ]);
        UserIdentity::create([
            'user_id' => $existing->id,
            'provider' => 'github',
            'provider_user_id' => '77777',
            'email' => 'existing@example.com',
            'email_verified' => true,
        ]);

        // Note: email is now different upstream — identity binding wins
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test'], 200),
            'https://api.github.com/user' => Http::response(['id' => 77777, 'login' => 'existing'], 200),
        ]);

        $state = 'state-rebind';
        session(['github_oauth_state' => $state]);

        $this->get("/oauth/github/callback?code=abc&state={$state}");

        $this->assertAuthenticatedAs($existing);
        $this->assertEquals(1, User::where('email', 'existing@example.com')->count());
        $this->assertEquals(1, UserIdentity::where('provider_user_id', '77777')->count());
    }

    // ---------- AUTH-04: callback error handling ----------

    public function test_github_callback_handles_upstream_failure_gracefully(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response('rate limited', 503),
        ]);

        $state = 'state-fail';
        session(['github_oauth_state' => $state]);

        $response = $this->get("/oauth/github/callback?code=abc&state={$state}");

        $response->assertRedirect('/');
    }

    // ---------- AUTH-05: PKCE required when challenge was bound ----------

    public function test_exchange_rejects_missing_verifier_when_challenge_bound(): void
    {
        $svc = app(OAuthService::class);
        $clientId = 'http://localhost:3000';
        $code = $svc->generateAuthorizationCode(
            $this->user,
            $clientId,
            $clientId.'/callback',
            'mcp:read',
            $this->challenge,
            'S256',
        );

        // Try to exchange with empty verifier — must fail even for non-
        // confidential clients
        $result = $svc->exchangeCode($code, '', $clientId, $clientId.'/callback');
        $this->assertNull($result);
    }

    // ---------- AUTH-06: SSRF via client_id-as-URL metadata fetch removed ----------

    public function test_unknown_url_client_id_no_longer_triggers_outbound_fetch(): void
    {
        $svc = app(OAuthService::class);

        // Note: NO Http::fake() registered. Any outbound HTTP would
        // attempt a real connection; assertSentCount(0) confirms zero.
        Http::fake();

        // Loopback-host redirect — falls through to dev fallback
        $this->assertTrue($svc->validateRedirectUri('http://app.example.org', 'http://localhost:3000/callback'));
        // Non-loopback redirect with unknown client → reject
        $this->assertFalse($svc->validateRedirectUri('http://attacker.example.org', 'https://evil.com/exfil'));

        Http::assertSentCount(0);
    }

    // ---------- AUTH-07: refresh-token rotation atomicity ----------

    public function test_refresh_token_replay_rejected_after_rotation(): void
    {
        $svc = app(OAuthService::class);
        $clientId = 'http://localhost:3000';
        $code = $svc->generateAuthorizationCode(
            $this->user,
            $clientId,
            $clientId.'/callback',
            'mcp:read',
            $this->challenge,
            'S256',
        );
        $token = $svc->exchangeCode($code, $this->verifier, $clientId, $clientId.'/callback');
        $raw = $token->raw_refresh_token;

        // First use succeeds
        $rotated = $svc->refreshToken($raw, $clientId);
        $this->assertNotNull($rotated);

        // Second use of the same refresh token must fail (single-use)
        $replay = $svc->refreshToken($raw, $clientId);
        $this->assertNull($replay);
    }

    // ---------- AUTH-08: authorization-code single-use atomicity ----------

    public function test_authorization_code_cannot_be_reused(): void
    {
        $svc = app(OAuthService::class);
        $clientId = 'http://localhost:3000';
        $code = $svc->generateAuthorizationCode(
            $this->user,
            $clientId,
            $clientId.'/callback',
            'mcp:read',
            $this->challenge,
            'S256',
        );

        $first = $svc->exchangeCode($code, $this->verifier, $clientId, $clientId.'/callback');
        $this->assertNotNull($first);

        $second = $svc->exchangeCode($code, $this->verifier, $clientId, $clientId.'/callback');
        $this->assertNull($second);
    }

    // ---------- AUTH-10: token revocation endpoint ----------

    public function test_revoke_endpoint_invalidates_access_token(): void
    {
        $svc = app(OAuthService::class);
        $clientId = 'http://localhost:3000';
        $code = $svc->generateAuthorizationCode(
            $this->user,
            $clientId,
            $clientId.'/callback',
            'mcp:read',
            $this->challenge,
            'S256',
        );
        $token = $svc->exchangeCode($code, $this->verifier, $clientId, $clientId.'/callback');

        $response = $this->postJson('/oauth/revoke', [
            'token' => $token->raw_token,
            'client_id' => $clientId,
        ]);
        $response->assertOk();

        // Token is gone, MCP returns 401
        $mcp = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => '1', 'method' => 'initialize', 'params' => [],
        ], ['Authorization' => 'Bearer '.$token->raw_token]);
        $mcp->assertStatus(401);

        // Refresh token is also gone (sibling revoked)
        $this->assertEquals(0, OAuthRefreshToken::count());
    }

    public function test_revoke_endpoint_responds_200_for_unknown_token(): void
    {
        // RFC 7009 §2.2: respond 200 even when nothing is revoked
        $response = $this->postJson('/oauth/revoke', [
            'token' => 'this-token-does-not-exist',
            'client_id' => 'http://localhost:3000',
        ]);
        $response->assertOk();
    }

    // ---------- AUTH-09: sensitive params not logged ----------

    public function test_oauth_authorize_does_not_log_sensitive_params(): void
    {
        // Spy on the log channel; before PB-2 the controller emitted
        // Log::info('OAuth authorize request', ['client_id','redirect_uri','state',...]).
        Log::spy();

        $this->actingAs($this->user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => 'http://localhost:3000',
            'redirect_uri' => 'http://localhost:3000/callback',
            'scope' => 'mcp:read',
            'code_challenge' => $this->challenge,
            'code_challenge_method' => 'S256',
            'state' => 'sensitive-state-marker',
        ]));

        Log::shouldNotHaveReceived('info');
    }
}
