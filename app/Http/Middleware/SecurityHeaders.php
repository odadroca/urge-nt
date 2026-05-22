<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * INFRA-02: defense-in-depth headers on every HTML/SPA response.
 *
 * Skipped on /api/* paths (NoCacheApi already runs there, and these
 * are JSON, not iframe/script targets). Skipped on /oauth/* and
 * /.well-known/* because their CORS/embedding semantics are governed
 * by OAuthCors.
 *
 * CSP is intentionally NOT a strict-dynamic mode — the existing SPA
 * uses inline scripts in Blade auth layouts and Vite-managed hashed
 * assets. The default policy below allows self + Vite's same-origin
 * outputs and blocks framing entirely.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
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

        // CSP — same-origin scripts only, no eval. `style-src` keeps
        // 'unsafe-inline' because the Breeze auth Blade layout has an
        // inline <style> for dark-mode bootstrap and Scalar (/docs)
        // injects styles at runtime.
        $headers['Content-Security-Policy'] = $this->csp(allowInlineStyle: true);

        foreach ($headers as $name => $value) {
            // Don't clobber values another middleware deliberately set
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function csp(bool $allowInlineStyle): string
    {
        // 'self' for scripts/styles/connects; explicitly no 'unsafe-eval'.
        // Style 'unsafe-inline' kept because the auth Blade layout has
        // an inline <style> for dark-mode bootstrap (cannot move to
        // external file without flash).
        $directives = [
            "default-src 'self'",
            "script-src 'self'",
            'style-src '.($allowInlineStyle ? "'self' 'unsafe-inline'" : "'self'"),
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
