<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B Sprint 8 verification — CSP nonce closure.
 *
 * PB-5 shipped a strict `script-src 'self'` CSP that would have blocked
 * the inline dark-mode bootstrap <script> in the Blade auth/public
 * layouts (the residual risk flagged in the PB-5 review). PB-8 fixes it
 * with a per-request nonce; these tests lock that in.
 */
class CspNoncePb8Test extends TestCase
{
    use RefreshDatabase;

    public function test_login_inline_script_carries_csp_nonce_matching_header(): void
    {
        $response = $this->get('/login');
        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);

        // Extract the header nonce and confirm the rendered inline <script>
        // uses the very same value (so the browser will execute it).
        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $csp, $m);
        $headerNonce = $m[1] ?? null;
        $this->assertNotNull($headerNonce);

        $this->assertStringContainsString(
            '<script nonce="'.$headerNonce.'">',
            $response->getContent(),
            'Inline dark-mode script must carry the same nonce advertised in the CSP header.'
        );
    }

    public function test_csp_keeps_script_src_strict_no_unsafe(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        // The whole point: no unsafe-inline / unsafe-eval on scripts.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_spa_shell_has_no_inline_scripts(): void
    {
        // The React SPA shell must stay inline-script-free so the strict
        // CSP never needs loosening for the primary surface.
        $shell = file_get_contents(resource_path('views/spa.blade.php'));
        $this->assertDoesNotMatchRegularExpression('/<script>(?!\s*<)/', $shell);
        $this->assertStringNotContainsString('<script>', $shell);
    }

    public function test_each_request_gets_a_distinct_nonce(): void
    {
        $a = $this->get('/login')->headers->get('Content-Security-Policy');
        $b = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertNotEquals($a, $b, 'CSP nonce must be per-request.');
    }
}
