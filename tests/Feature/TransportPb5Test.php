<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B Sprint 5 regression tests — web-transport, config, CORS,
 * health, OpenAPI templatization, and MCP origin findings
 * (INFRA-01..11, MCP-05).
 */
class TransportPb5Test extends TestCase
{
    use RefreshDatabase;

    // ---------- INFRA-02: security headers on web responses ----------

    public function test_security_headers_present_on_web_response(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function test_csp_blocks_framing_and_objects(): void
    {
        $csp = $this->get('/up')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_security_headers_skip_api_json(): void
    {
        // /api/* is JSON, not an iframe/script target; SecurityHeaders skips it
        $response = $this->getJson('/api/v1/health');
        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    // ---------- INFRA-11: minimal health endpoint ----------

    public function test_up_health_is_minimal_json_without_framework_branding(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
        $this->assertStringNotContainsString('Laravel', $response->getContent());
        // No external resources loaded
        $this->assertStringNotContainsString('fonts.bunny.net', $response->getContent());
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $response->getContent());
    }

    // ---------- INFRA-01: Scalar self-hosted, no CDN ----------

    public function test_docs_page_does_not_load_scalar_from_cdn(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $response->getContent());
        // Vite tag for the bundled scalar entry is present
        $this->assertStringContainsString('scalar', $response->getContent());
    }

    // ---------- INFRA-05: OpenAPI server URL templatized ----------

    public function test_openapi_server_url_uses_app_url_not_hardcoded_domain(): void
    {
        config(['app.url' => 'https://my-instance.example.test']);

        $response = $this->get('/openapi.json');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('urge.acordado.org', $body);
        $this->assertStringNotContainsString('{{APP_URL}}', $body);
        $this->assertStringContainsString('https://my-instance.example.test/api/v1', $body);
    }

    // ---------- INFRA-03: CORS allowlist ----------

    public function test_cors_preflight_allows_configured_origin(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.example.com']]);

        $response = $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN' => 'https://allowed.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertEquals('https://allowed.example.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_does_not_reflect_unlisted_origin(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.example.com']]);

        $response = $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNotEquals('https://evil.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    // ---------- MCP-05: MCP Origin allowlist ----------

    public function test_mcp_rejects_arbitrary_https_origin(): void
    {
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => '1', 'method' => 'initialize', 'params' => [],
        ], ['Origin' => 'https://evil.example.com']);

        $response->assertStatus(403)
            ->assertJsonPath('error.message', 'Origin not allowed.');
    }

    public function test_mcp_allows_claude_ai_origin_but_still_requires_auth(): void
    {
        // Allowed origin passes the Origin gate, then hits the auth gate (401)
        $response = $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0', 'id' => '1', 'method' => 'initialize', 'params' => [],
        ], ['Origin' => 'https://claude.ai']);

        $response->assertStatus(401);
    }

    // ---------- INFRA-06: build artifacts no longer tracked ----------

    public function test_public_build_is_gitignored(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));
        // The line must be active (not commented)
        $this->assertMatchesRegularExpression('/^\/public\/build$/m', $gitignore);
    }

    // ---------- INFRA-04 / INFRA-09: .env.example secure defaults ----------

    public function test_env_example_ships_secure_defaults(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $env);
    }
}
