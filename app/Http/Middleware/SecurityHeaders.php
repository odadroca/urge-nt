<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * INFRA-02: defense-in-depth headers on every web (HTML/SPA) response.
 *
 * Skipped on /api/* (JSON, NoCacheApi runs there), /oauth/* and
 * /.well-known/* (governed by OAuthCors).
 *
 * CSP keeps `script-src` strict ('self' + a per-request nonce — no
 * 'unsafe-inline', no 'unsafe-eval'). The React SPA shell (spa.blade.php)
 * has zero inline scripts, so it's compliant out of the box. The Blade
 * auth/public layouts carry a small inline dark-mode bootstrap <script>;
 * those tags emit `nonce="{{ $cspNonce }}"` so they validate against the
 * same nonce advertised in the header (PB-8 fix — the strict policy would
 * otherwise block them in the browser).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the downstream view renders so Blade can stamp
        // the same nonce onto inline <script> tags.
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        if ($request->isSecure() || config('app.env') === 'production') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $headers['Content-Security-Policy'] = $this->csp($nonce);

        foreach ($headers as $name => $value) {
            // Don't clobber values another middleware deliberately set
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function csp(string $nonce): string
    {
        // 'self' + nonce for scripts; no 'unsafe-inline', no 'unsafe-eval'.
        // 'style-src' keeps 'unsafe-inline' because Scalar (/docs) injects
        // <style> at runtime and the dark-mode bootstrap uses an inline
        // <style> — JS-injected styles can't carry a nonce.
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('api/*')
            || $request->is('oauth/*')
            || $request->is('.well-known/*');
    }
}
