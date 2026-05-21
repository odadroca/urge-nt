# PB-5 — Web transport, deployment hardening & CI

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes INFRA-01..11 + MCP-05.
**Suite:** 491 → **503 passing** (12 new regression tests).

Fifth Phase B sprint — Theme E (defense-in-depth at the HTTP layer) plus
the deploy/CI process gaps. A single XSS or CDN compromise previously had
nothing between it and the full corpus; PB-5 adds the missing layers.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **INFRA-01** | High | Scalar is now **self-hosted**: added `@scalar/api-reference@1.57.3` as a dependency, bundled via a new Vite entry `resources/js/scalar.js` using the programmatic `createApiReference()` API. `/docs` no longer loads anything from `cdn.jsdelivr.net`. No CDN → no SRI needed, supply-chain risk eliminated. |
| **INFRA-02** | High | New `App\Http\Middleware\SecurityHeaders` appended to the `web` group: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `Strict-Transport-Security` (when secure/prod), and a `Content-Security-Policy` with `frame-ancestors 'none'`, `object-src 'none'`, `default-src 'self'`, no `unsafe-eval`. Skips `/api/*`, `/oauth/*`, `/.well-known/*`. |
| **INFRA-03** | High | `config/cors.php` rewritten: env-driven `CORS_ALLOWED_ORIGINS` allowlist (same-origin + loopback defaults), explicit method/header lists, no more `['*']` with `supports_credentials=true`. |
| **INFRA-04** | High | `.env.example` ships `APP_DEBUG=false` (was `true`). |
| **INFRA-05** | High | `public/openapi.json` moved to `resources/openapi.json` (the static file in `public/` shadowed any route). Served via a new `/openapi.json` web route that injects `APP_URL` into the `{{APP_URL}}` placeholder at request time — no hardcoded `urge.acordado.org` baked into forks. |
| **INFRA-06** | Med | `.gitignore` line `/public/build` un-commented; the 5 tracked build artifacts untracked (`git rm --cached`). |
| **INFRA-07** | Med | New `.github/workflows/ci.yml`: backend job (composer install → migrate → `pint --test` → `php artisan test` → `composer audit`) + frontend job (`npm ci` → build → `npm audit --audit-level=high`). |
| **INFRA-08** | Med | `deploy/hostinger.sh` rewritten with `set -Eeuo pipefail`, pre-deploy SQLite backup (10-deep retention), a frontend build step (since `public/build` is no longer committed), a post-deploy `/up` health check, and an `ERR`-trap that rolls back **both code (`git reset --hard PREV`) and the DB** on any failure. |
| **INFRA-09** | Med | `.env.example`: `SESSION_ENCRYPT=true`, documented `SESSION_SECURE_COOKIE=true` (commented, "behind HTTPS"), explicit `SESSION_HTTP_ONLY`/`SESSION_SAME_SITE`. |
| **INFRA-10** | Low | `.env.example` expanded with `CORS_ALLOWED_ORIGINS`, `MCP_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, and the template/LLM safety knobs (from PB-3/PB-4). |
| **INFRA-11** | Low | The framework `/up` health page (branded Laravel HTML loading external fonts + a CDN script) replaced with a minimal `Route::get('/up', fn () => response()->json(['status'=>'ok']))` inside the web group (so it also gets SecurityHeaders). `health: '/up'` removed from `withRouting`. |
| **MCP-05** | Med | `McpController::isAllowedOrigin` no longer returns true for any `https://` origin. Now: same-origin + loopback + a config list (Claude.ai, Mistral Le Chat, chrome-extension) + operator-supplied `MCP_ALLOWED_ORIGINS`. |

---

## Architecture

### New files
- `app/Http/Middleware/SecurityHeaders.php`
- `resources/js/scalar.js` (bundled Scalar entry)
- `.github/workflows/ci.yml`
- `tests/Feature/TransportPb5Test.php` (12 tests)
- `resources/openapi.json` (moved from `public/`)

### Modified files
- `bootstrap/app.php` — register `SecurityHeaders` on `web`; drop
  `health: '/up'`; add `oauth/revoke` to CSRF exemptions (carried from PB-2).
- `config/cors.php` — env-driven allowlist.
- `resources/views/docs.blade.php` — `@vite('resources/js/scalar.js')`,
  no CDN script.
- `vite.config.js` — `resources/js/scalar.js` added to inputs.
- `routes/web.php` — minimal `/up`; templatized `/openapi.json`.
- `routes/api.php` — old static-file openapi route removed.
- `app/Http/Controllers/McpController.php` — tight Origin allowlist.
- `.env.example` — secure defaults + documented knobs.
- `.gitignore` — `/public/build` active; `/storage/deploy-backups`.
- `deploy/hostinger.sh` — backup + build + health check + rollback.
- `package.json` / `package-lock.json` — `@scalar/api-reference` dep.
- `README.md` — OpenAPI spec link points to `resources/openapi.json`.

### Decision points

- **Self-host > SRI.** The audit suggested "pin + SRI or self-host."
  Outbound CDN access is blocked in this environment (couldn't compute
  an SRI hash anyway), and self-hosting is strictly stronger — it
  removes the third-party origin entirely. The Scalar bundle is large
  (~3 MB / 922 KB gzip) but it's only loaded on `/docs`.
- **CSP keeps `style-src 'unsafe-inline'`.** The Breeze auth Blade
  layout has an inline `<style>` for dark-mode bootstrap (prevents
  flash). Script is locked to `'self'` (no inline JS, no eval). A
  nonce-based style policy is a future refinement.
- **`/openapi.json` moved out of `public/`.** A physical file in
  `public/` is served directly by the web server / `php artisan serve`,
  shadowing any Laravel route — so the placeholder substitution only
  works once the file lives in `resources/` and is served through the
  route.
- **CI `pint --test` requires a clean tree.** The repo was never
  Pint-formatted; a follow-up commit applies a repo-wide `vendor/bin/pint`
  pass so the new lint gate is green from day one. That pass is kept
  **separate** from this functional commit to keep the security diff
  reviewable.
- **Deploy rollback restores the DB too.** Migrations run before the
  health check; a failed deploy reverts code AND restores the
  pre-deploy SQLite snapshot, so a bad migration can't leave a
  half-migrated production DB.

---

## Test coverage added

`tests/Feature/TransportPb5Test.php` (12 tests): security headers present
on web responses and absent on `/api/*` JSON; CSP blocks framing/objects;
`/up` is minimal JSON with no "Laravel"/CDN strings; `/docs` loads no
jsdelivr CDN; `/openapi.json` uses `APP_URL` (no hardcoded domain, no
unresolved placeholder); CORS preflight allows a configured origin and
does not reflect an unlisted one; MCP rejects an arbitrary HTTPS origin
(403) but lets Claude.ai through to the auth gate (401); `/public/build`
is gitignored; `.env.example` ships `APP_DEBUG=false` + `SESSION_ENCRYPT=true`.

---

## Verification

```
$ php artisan test
Tests:    503 passed (1329 assertions)
$ npm run build      # bundles self-hosted Scalar, succeeds
```

Zero existing-suite regressions.

---

## Next

PB-6 — Documentation regeneration + dead-code removal (Themes G, H):
LICENSE, SECURITY.md, count/domain drift, AiAssistantService removal,
unused middleware aliases. Then PB-7 (dependency upgrades) and PB-8
(verification & closure).
