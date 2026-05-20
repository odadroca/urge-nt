# Sprint 6 — Web Transport, Config & Deployment Hardening

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: CORS, security headers, session/sanctum cookies, CSRF, debug mode,
public OpenAPI/docs surface, deploy script, CI/CD presence, build
artifacts in VCS, dependency baseline, env-knob documentation, HYGIENE-01
history verification.

Files reviewed: `config/{cors,session,sanctum,urge}.php`, `.env.example`,
`bootstrap/app.php`, `app/Http/Middleware/{NoCacheApi,OAuthCors}.php`,
`public/{.htaccess,openapi.json,build/*}`,
`resources/views/docs.blade.php`, `deploy/hostinger.sh`,
`vite.config.js`, git log of `storage/logs/laravel.log`.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### INFRA-01 — Public `/docs` loads `@scalar/api-reference` from CDN with **no SRI integrity, no version pin**, no auth — **HIGH / Security**

`resources/views/docs.blade.php:34`:
`<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>`.
The `/docs` route is public (Sprint 0 inventory). No `integrity=`, no
version pin (latest tag), no fallback. Any compromise of jsdelivr or the
upstream Scalar package → arbitrary JS executes for every visitor to a
URGE instance's `/docs`. The page sits same-origin as the SPA and OAuth
flows, so a malicious payload can phish/redirect users mid-flow.

Fix (Phase B): pin version, add `integrity` (SRI) hash; or self-host the
script (commit to `public/build/`); or gate `/docs` behind auth.

---

### INFRA-02 — No security headers anywhere (CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) — **HIGH / Security**

No middleware sets any of these on web/API/SPA responses.
`NoCacheApi.php:15-16` sets cache headers only. Implications:

- **Clickjacking:** no `X-Frame-Options`/`frame-ancestors` → URGE pages
  can be iframed by any site → OAuth consent UI is clickjackable
  (`/oauth/authorize` is on the auth-required group, so a victim's
  cookies will attach in lax-mode top-level navigation but not within an
  iframe by default — still, no defense-in-depth).
- **MIME sniffing** (`X-Content-Type-Options: nosniff`): missing.
- **HSTS** missing → downgrade attacks possible on first visit.
- **CSP** missing → in conjunction with INFRA-01, a single XSS or CDN
  compromise has no in-browser containment.
- **Referrer-Policy** missing → URGE prompts/result IDs leak in
  Referer to upstream LLM endpoints (each driver makes outbound calls
  with the Referer header automatically populated by curl/Laravel
  defaults).

Fix (Phase B): single middleware (e.g. `SecurityHeaders`) appended to web
and api groups, with a strict CSP for `/docs` and the SPA.

---

### INFRA-03 — CORS config wildcards `Origin/Headers/Methods` with `supports_credentials=true` — **HIGH / Security (intent)**

`config/cors.php:18-32`:
- `'paths' => ['api/*', 'sanctum/csrf-cookie']`
- `'allowed_methods' => ['*']`, `'allowed_origins' => ['*']`,
  `'allowed_headers' => ['*']`
- `'supports_credentials' => true`

CORS spec forbids `Access-Control-Allow-Origin: *` together with
`Access-Control-Allow-Credentials: true`; modern browsers reject the
combination, so today credentialed cross-origin XHRs from arbitrary sites
**fail at the browser**. But the *config intent* is permissive — a
library upgrade or a `allowed_origins_patterns` regex change will flip
this to actively allow cross-origin credentialed access to every
`/api/*` endpoint. Non-credentialed cross-origin reads of `/api/*`
responses succeed today (e.g. public health/openapi, anything that
doesn't require auth).

Combined with INFRA-02 (no security headers), this is a tripwire that
will trip on the next change.

Fix (Phase B): explicit allowlist of origins (env-driven); narrow
allowed methods/headers to what the SPA actually needs; remove the `*`
wildcard while `supports_credentials=true`.

---

### INFRA-04 — `.env.example: APP_DEBUG=true` default — **HIGH / Security**

`.env.example:4` ships `APP_DEBUG=true`. Operators copying `.env.example
→ .env` and forgetting to flip the flag publish full stack traces, env
dumps, and query data via Laravel's error page (and historical Ignition
RCEs hit debug-mode-only). The README quick-start instructs
`cp .env.example .env` without mentioning the flip.

Fix (Phase B): `APP_DEBUG=false` in `.env.example`; document the local-
dev override in README; refuse `APP_DEBUG=true` outside `APP_ENV=local`
via a deploy guard.

---

### INFRA-05 — `public/openapi.json` is publicly served + hardcoded production server URL — **HIGH / Security + Drift**

`public/openapi.json:9-11`:
`"servers": [{ "url": "https://urge.acordado.org/api/v1" }]`. Served at
`/openapi.json` and `/api/v1/openapi.json` (`routes/api.php:172-178`).
Public, 2149-line full endpoint catalogue — a complete recon aid on any
URGE instance. Hardcoded domain bakes the upstream operator's instance
into every fork (matches the Sprint 0 doc-agent finding; cross-references
documentation/claude-skill.md:8 hardcoded base URL).

Fix (Phase B): templatize the server URL with `{appUrl}` at render time;
gate `/openapi.json` and `/docs` behind auth (or, at minimum, behind a
config switch defaulting off in production).

---

### INFRA-06 — Compiled `public/build/*` is committed to the repo (and `.gitignore`'s `public/build` line is commented out) — **MED / Bug + DeadEnd**

`.gitignore:1` is `# /public/build` (a comment — does not ignore). 5
build artifacts are tracked
(`public/build/{assets/app-*.js, main-*.js, *.css, manifest.json}`).
Consequences:

- Every UI change requires a paired rebuild commit; review noise; merge
  conflicts on hashed filenames.
- Stale bundles persist in history even after source changes; CI without
  rebuild would deploy outdated UI.
- Risk of accidentally committing dev-mode bundles with source maps.

Fix (Phase B): uncomment `/public/build` in `.gitignore`; remove tracked
bundles; add a build step to deploy script (or CI).

---

### INFRA-07 — No CI/CD: `.github/` absent — **MED / Process**

417-test suite passing locally, but **no automated gate**: no GitHub
Actions, no pre-merge tests/lint/security scan. Regressions on the audit
findings ship freely. Also: Phase B remediation has no enforcement that
fixes don't bring new regressions.

Fix (Phase B): minimal `.github/workflows/ci.yml` running
`php artisan test`, `pint --test`, `npm run build`, `composer audit`,
`npm audit`; later add SAST (`larastan`/`psalm`) and a security-headers
check.

---

### INFRA-08 — Deploy script lacks DB backup, rollback, atomicity — **MED / Operations**

`deploy/hostinger.sh`:
- `set -e` (line 10) exits on first failure → **half-deployed state**
  (e.g. `migrate` ran but `view:cache` failed; new code + stale cache).
- No DB snapshot before `migrate --force` (line 30).
- No rollback path.
- `rsync --delete` on `public/` (line 34) → if rsync fails mid-flight,
  document root is partial.
- No structured deploy log; output to stdout only.
- No health check post-deploy.

Fix (Phase B): pre-deploy sqlite copy + retention; deploy to staging
dir + atomic symlink swap; post-deploy `/up` check + rollback symlink.

---

### INFRA-09 — `SESSION_ENCRYPT=false` in `.env.example`; `SESSION_SECURE_COOKIE` unset — **MED / Security**

`.env.example:32` `SESSION_ENCRYPT=false`; `config/session.php:172`
`env('SESSION_SECURE_COOKIE')` with no default ⇒ false. Implications:

- Plaintext sessions in DB (DB dump = session theft, incl. CSRF tokens,
  flash data, any app-stashed context).
- Session cookies sent over HTTP if available — operator can deploy
  HTTPS-only and forget to set the env. No app-level enforcement.

Fix (Phase B): set `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true`
in `.env.example`; refuse `secure=false` outside `local` env via a
deploy guard.

---

### INFRA-10 — Many env knobs undocumented in `.env.example` — **LOW / Drift**

Missing entries: `SESSION_SECURE_COOKIE`, `SANCTUM_STATEFUL_DOMAINS`,
`CURL_SSL_VERIFY` (LLM-09), `URGE_MAX_INCLUDE_DEPTH`,
`URGE_MAX_COLLECTION_DEPTH`, `URGE_UNLIMITED_COLLECTION_DEPTH`,
`URGE_API_RATE_LIMIT`, `URGE_API_RATE_WINDOW`,
`URGE_KEY_PREFIX/BYTES/PREVIEW_LENGTH`. Operators cannot tune
security/limits without reading config files.

Fix (Phase B): expand `.env.example` with all knobs + inline comments.

---

### INFRA-11 — Health endpoints expose framework — **LOW / Security**

`/up` (Laravel default, `bootstrap/app.php:12`) returns Laravel's
welcome health page — leaks framework identity. `/api/v1/health` is
custom and OK. Combined with INFRA-02 (no headers), an attacker
fingerprints stack in one HEAD request.

Fix (Phase B): replace `/up` body with a minimal `{"status":"ok"}`;
optionally restrict to LB/internal IPs.

---

### INFRA-12 — HYGIENE-01 history verification — **closed**

Sprint 0 finding: `storage/logs/laravel.log` was VCS-tracked. Verified
historical content: 216 lines from one prior commit (`ae12002`); grep
for `token|secret|key|password|bearer|authorization` returned **zero
matches**. The privacy risk was real but **did not materialize as a
secret leak** in this repo's history. HYGIENE-01 fix (Sprint 0) stands;
no history rewrite required for this repo.

---

## Out-of-scope notes

- Sourcemaps: `vite.config.js` is minimal; build emits no `*.map` in
  `public/build/` — confirmed. Good.
- `NoCacheApi` correctly applied to all `/api/*` responses (positive).
- CSRF exempts (`bootstrap/app.php:22-25`) are correctly limited to
  `oauth/token`, `oauth/register` (positive; see AUTH-02 for the
  separate rate-limit gap).
- `.htaccess` denies `Indexes` and `MultiViews`, normalizes
  Authorization header (positive).
- `OAuthCors` reflects `Origin` for OAuth/discovery endpoints — correct
  by MCP/OAuth spec (positive). Distinct from `config/cors.php`'s
  permissive intent (INFRA-03).
- Build hashed filenames provide cache-busting (positive).
- Dependency CVEs DEP-01/02/03 carry forward from earlier sprints; no
  new advisories in scope here.

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| INFRA-01 | High | Security | resources/views/docs.blade.php:34 |
| INFRA-02 | High | Security | (no middleware sets CSP/HSTS/XFO/XCTO/Referrer-Policy) |
| INFRA-03 | High | Security | config/cors.php:18-32 |
| INFRA-04 | High | Security | .env.example:4 |
| INFRA-05 | High | Security/Drift | public/openapi.json:9-11 |
| INFRA-06 | Med | Bug/DeadEnd | .gitignore:1; public/build/* (5 files) |
| INFRA-07 | Med | Process | absent .github/workflows |
| INFRA-08 | Med | Operations | deploy/hostinger.sh |
| INFRA-09 | Med | Security | .env.example:32; config/session.php:172 |
| INFRA-10 | Low | Drift | .env.example (missing knobs) |
| INFRA-11 | Low | Security | bootstrap/app.php:12 (`/up`) |
| INFRA-12 | — | (closed) | HYGIENE-01 history verified; no leak |

**Cross-cutting:** the transport layer has no defense-in-depth — CSP,
HSTS, frame-ancestors, SRI on third-party scripts, and CORS allowlists
are all missing or permissive. Combined with the AUTHZ IDORs (Sprint 2)
and the credential-exfil chain (Sprint 4 LLM-01/02/03), a single XSS in
the SPA or a CDN compromise on the `/docs` script has nothing standing
between attacker and the full prompt/result corpus. Phase B should ship
a `SecurityHeaders` middleware and tighten CORS as the first cheap
hardening pass.

## Next

Sprint 7 — Documentation, Dead-Ends & Drift (report-only). Pending user
review.
