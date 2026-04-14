<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\User;
use App\Services\OAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Prompt $prompt;
    private string $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    private string $challenge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'OAuth User',
            'email'    => 'oauth@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->challenge = rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');

        $this->prompt = Prompt::create([
            'name'       => 'OAuth Test',
            'type'       => 'prompt',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_well_known_protected_resource(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $response->assertStatus(200)
            ->assertJsonStructure(['resource', 'authorization_servers', 'scopes_supported']);
    }

    public function test_well_known_authorization_server(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertStatus(200)
            ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'code_challenge_methods_supported'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_authorize_shows_consent_page(): void
    {
        $response = $this->actingAs($this->user)->get('/oauth/authorize?' . http_build_query([
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:read',
            'state'                 => 'test-state',
            'code_challenge'        => $this->challenge,
            'code_challenge_method' => 'S256',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Authorize Application');
        $response->assertSee('mcp:read');
    }

    public function test_full_oauth_flow_with_pkce(): void
    {
        // Step 1: Approve authorization
        $response = $this->actingAs($this->user)->post('/oauth/authorize', [
            '_token'                => csrf_token(),
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:read',
            'state'                 => 'test-state',
            'code_challenge'        => $this->challenge,
            'code_challenge_method' => 'S256',
            'decision'              => 'approve',
        ]);

        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('http://localhost:3000/callback', $redirectUrl);

        // Step 2: Extract code from redirect
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);
        $this->assertArrayHasKey('code', $queryParams);
        $code = $queryParams['code'];

        // Step 3: Exchange code for token
        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $this->verifier,
            'client_id'     => 'http://localhost:3000',
            'redirect_uri'  => 'http://localhost:3000/callback',
        ]);

        $tokenResponse->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'scope']);

        $accessToken = $tokenResponse->json('access_token');

        // Step 4: Use token on MCP initialize
        $mcpResponse = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id'      => '1',
            'method'  => 'initialize',
            'params'  => [],
        ], ['Authorization' => "Bearer {$accessToken}"]);

        $mcpResponse->assertStatus(200)
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_pkce_invalid_verifier_rejected(): void
    {
        // Approve authorization
        $response = $this->actingAs($this->user)->post('/oauth/authorize', [
            '_token'                => csrf_token(),
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:read',
            'state'                 => 'test-state',
            'code_challenge'        => $this->challenge,
            'code_challenge_method' => 'S256',
            'decision'              => 'approve',
        ]);

        $redirectUrl = $response->headers->get('Location');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $queryParams);
        $code = $queryParams['code'];

        // Exchange with WRONG verifier
        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => 'wrong-verifier-that-does-not-match-the-challenge',
            'client_id'     => 'http://localhost:3000',
            'redirect_uri'  => 'http://localhost:3000/callback',
        ]);

        $tokenResponse->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_deny_authorization(): void
    {
        $response = $this->actingAs($this->user)->post('/oauth/authorize', [
            '_token'                => csrf_token(),
            'client_id'             => 'http://localhost:3000',
            'redirect_uri'          => 'http://localhost:3000/callback',
            'scope'                 => 'mcp:read',
            'state'                 => 'test-state',
            'code_challenge'        => $this->challenge,
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

        $response->assertStatus(401);
        $wwwAuth = $response->headers->get('WWW-Authenticate');
        $this->assertNotNull($wwwAuth);
        $this->assertStringContainsString('resource_metadata=', $wwwAuth);
    }

    public function test_scope_enforcement_blocks_admin_with_read_token(): void
    {
        $oauthService = app(OAuthService::class);

        $code = $oauthService->generateAuthorizationCode(
            $this->user,
            'http://localhost:3000',
            'http://localhost:3000/callback',
            'mcp:read',
            $this->challenge,
            'S256',
        );

        $token = $oauthService->exchangeCode(
            $code,
            $this->verifier,
            'http://localhost:3000',
            'http://localhost:3000/callback',
        );

        $this->assertNotNull($token);

        // Try to call delete_prompt (requires mcp:admin) with a read-only token
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id'      => '1',
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'delete_prompt',
                'arguments' => ['slug' => $this->prompt->slug],
            ],
        ], ['Authorization' => "Bearer {$token->raw_token}"]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Insufficient scope', $response->json('result.content.0.text'));
    }

    public function test_dynamic_client_registration_returns_client_id(): void
    {
        $response = $this->postJson('/oauth/register', [
            'redirect_uris' => ['http://127.0.0.1:3000/callback'],
            'client_name' => 'Test Client',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['client_id', 'client_name', 'redirect_uris', 'client_id_issued_at'])
            ->assertJsonPath('client_name', 'Test Client');
    }

    public function test_dynamic_registration_rejects_missing_redirect_uris(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Bad Client',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'invalid_client_metadata');
    }

    public function test_dynamic_registration_rejects_non_loopback_http_redirect(): void
    {
        $response = $this->postJson('/oauth/register', [
            'redirect_uris' => ['http://evil.com/callback'],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'invalid_client_metadata');
    }

    public function test_dynamic_registration_allows_https_redirect(): void
    {
        $response = $this->postJson('/oauth/register', [
            'redirect_uris' => ['https://example.com/callback'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['client_id']);
    }

    public function test_full_oauth_flow_with_dynamic_registration(): void
    {
        $this->actingAs($this->user);

        // Step 1: Register client
        $regResponse = $this->postJson('/oauth/register', [
            'redirect_uris' => ['http://127.0.0.1:3000/callback'],
            'client_name' => 'E2E Test',
        ]);
        $regResponse->assertStatus(201);
        $clientId = $regResponse->json('client_id');

        // Step 2: PKCE
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Step 3: Authorize
        $authResponse = $this->post('/oauth/authorize', [
            'client_id' => $clientId,
            'redirect_uri' => 'http://127.0.0.1:3000/callback',
            'scope' => 'mcp:write',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'decision' => 'approve',
        ]);
        $authResponse->assertRedirect();
        parse_str(parse_url($authResponse->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('code', $query);

        // Step 4: Token exchange
        $tokenResponse = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $query['code'],
            'code_verifier' => $verifier,
            'client_id' => $clientId,
            'redirect_uri' => 'http://127.0.0.1:3000/callback',
        ]);
        $tokenResponse->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);

        // Step 5: Use token on MCP
        $mcpResponse = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'initialize',
            'params' => [],
        ], ['Authorization' => 'Bearer ' . $tokenResponse->json('access_token')]);

        $mcpResponse->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_registered_client_rejects_unregistered_redirect_uri(): void
    {
        $this->actingAs($this->user);

        // Register with one URI
        $regResponse = $this->postJson('/oauth/register', [
            'redirect_uris' => ['http://127.0.0.1:3000/callback'],
        ]);
        $clientId = $regResponse->json('client_id');

        $verifier = 'test-verifier-string-for-redirect-check';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Try to authorize with a DIFFERENT URI
        $response = $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'http://127.0.0.1:9999/evil',
            'scope' => 'mcp:read',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        // Should redirect with error (redirect_uri not allowed)
        $response->assertRedirect('/');
    }

    public function test_well_known_includes_registration_endpoint(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertOk()
            ->assertJsonStructure(['registration_endpoint']);
    }

    public function test_existing_url_based_client_id_still_works(): void
    {
        $this->actingAs($this->user);

        $verifier = 'backward-compat-verifier-test';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Use a non-registered localhost client_id (should work via fallback)
        $response = $this->get('/oauth/authorize?' . http_build_query([
            'client_id' => 'http://localhost:4000',
            'redirect_uri' => 'http://localhost:4000/callback',
            'scope' => 'mcp:read',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $response->assertOk()
            ->assertSee('Authorize Application');
    }
}
