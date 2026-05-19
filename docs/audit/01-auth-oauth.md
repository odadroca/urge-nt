# Sprint 1 — Authentication & OAuth 2.1

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: OAuth 2.1 authorization-code + refresh + dynamic registration, PKCE,
GitHub external IdP, dual-auth middleware, API-key auth, token storage.

Files reviewed: `app/Http/Controllers/OAuthController.php`,
`app/Services/OAuthService.php`,
`app/Http/Controllers/OAuthGitHubController.php`,
`app/Http/Middleware/{DualAuthentication,ApiKeyAuthentication}.php`,
`app/Services/ApiKeyService.php`, `app/Models/{OAuth*,ApiKey}.php`,
`routes/{web,api,auth}.php`, `bootstrap/app.php`, `config/urge.php`.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### AUTH-01 — Authorization code issued to unvalidated `redirect_uri` (open redirect → code theft / ATO) — **HIGH / Security**

Two code-issuing paths skip the redirect-URI allowlist that
`showAuthorize` enforces (`OAuthController.php:56`,
`OAuthService::validateRedirectUri`):

1. **GitHub path.** `OAuthGitHubController::redirect()` stores raw query
   params (`client_id`, `redirect_uri`, `code_challenge`, …) into the session
   with **no validation** (`OAuthGitHubController.php:25-28`). `callback()`
   then mints an authorization code via `generateAuthorizationCode()` and
   `redirect($oauthParams['redirect_uri'] . '?code=…&state=…')`
   (`:90-105`) — `validateRedirectUri()` / `findClient()` are never called.
2. **Consent POST.** `handleAuthorize()` validates `redirect_uri` only as a
   well-formed URL (`OAuthController.php:83`) and issues the code to it
   (`:100-119`) **without** re-checking it against the client's registered
   URIs (the GET `showAuthorize` check is not re-applied on the POST that
   actually issues the code).

Impact: an attacker crafts
`/oauth/authorize?client_id=<any|self-registered>&redirect_uri=https://evil.tld&...`,
lures an authenticated victim (or via "Login with GitHub"), and the issued
authorization `code` is delivered to the attacker URL. Combined with an
attacker-registered confidential client (AUTH-02 open registration) the code
is exchanged for tokens → **MCP session / account takeover**. RFC 6749 §10.6.

Fix (Phase B): enforce `validateRedirectUri($clientId,$redirectUri)` in
`handleAuthorize()` **and** in the GitHub `callback()` before
`generateAuthorizationCode`/redirect; reject on mismatch. Bind the issued code
to the validated client.

---

### AUTH-02 — No rate limiting on `/oauth/token` and `/oauth/register`; open client registration — **HIGH / Security**

`routes/web.php:67-68` register `oauth/token` and `oauth/register` with **no
`throttle` middleware** and they are CSRF-exempt (`bootstrap/app.php:22-25`,
correct per spec). Contrast password-reset routes which use `throttle:6,1`
(`routes/auth.php:43,47`).

- `/oauth/register` (RFC 7591) is **unauthenticated and unthrottled** →
  unbounded `oauth_clients` row creation (DB exhaustion / spam) and lets any
  party self-issue a confidential client (`client_secret`) — the enabler for
  AUTH-01 code exchange and AUTH-05 PKCE bypass.
- `/oauth/token` unthrottled → brute-force surface for
  `code` / `client_secret` / `refresh_token` and a free amplification point.

Fix (Phase B): add `throttle` to both (e.g. `throttle:10,1` token,
stricter for register); consider requiring software-statement / admin approval
or per-IP cap on registration; cap total clients.

---

### AUTH-03 — GitHub auto-provisioning trusts unverified email → account takeover — **HIGH / Security**

`OAuthGitHubController::callback()` `:67-86`:
`$email = $githubUser['email']` (or first `primary` from `/user/emails` — the
`verified` flag is **not** checked), then
`User::where('email',$email)->first()` → `Auth::login($user)` or `User::create`.

- If a password-based URGE account exists for email *X*, anyone who can set
  *X* as a (even unverified) GitHub email logs in as that user **without the
  password** → full ATO.
- Pre-hijack: attacker provisions a URGE account for a victim's email before
  they sign up.
- `User` does **not** implement `MustVerifyEmail` (commented at
  `app/Models/User.php:5`); `email_verified_at` is never set on the
  GitHub-created or Breeze-registered user (`RegisteredUserController.php:39`
  fires `Registered` but verification is not enforced anywhere).

Fix (Phase B): require `verified === true` on the chosen GitHub email; do
**not** auto-link an existing local account to a GitHub identity without an
explicit, authenticated link step; persist a provider identity (provider+sub)
rather than matching on email; enforce `MustVerifyEmail` for password signups.

---

### AUTH-04 — GitHub callback: unguarded array access & no HTTP error handling — **MED / Bug**

`callback()` makes four `Http::` calls (`:55,66,70`) with **no `successful()`
checks**; `$githubUser['email']` (`:67`) and
`$githubUser['name'] ?? $githubUser['login']` (`:82`) assume keys exist. On
GitHub rate-limit/revoked-token/error JSON these raise undefined-index warnings
or create a user with a null name; flow can 500 mid-auth. Robustness +
error-path info leak.

Fix (Phase B): check `$resp->successful()`, validate JSON shape, fail closed
with a user-facing error.

---

### AUTH-05 — PKCE bypass by design for confidential clients + empty-challenge codes — **MED / Security**

`OAuthService::exchangeCode()` `:56-70`: if the client has a `client_secret`
it validates the secret and **ignores PKCE entirely**, even when the code was
created with a `code_challenge`. `generateAuthorizationCode()` defaults
`code_challenge => ''` when null (`:31`). The GitHub path (AUTH-01) can mint
codes with an empty challenge; an attacker-registered confidential client then
exchanges them with no PKCE proof. The code is bound to the client only by
`client_id` equality on the row — not cryptographically to the requesting
client.

Fix (Phase B): if a `code_challenge` was bound, require a matching
`code_verifier` regardless of client type; reject exchange of codes with empty
challenge for public clients; treat secret + PKCE as additive when both
present.

---

### AUTH-06 — Blind SSRF via `client_id`-as-URL metadata fetch — **MED / Security**

`OAuthService::fetchClientMetadata()` `:194-213` does
`Http::timeout(5)->get($clientId)` whenever `client_id` passes
`FILTER_VALIDATE_URL`, with **no scheme/host allowlist and no block of
loopback / RFC1918 / link-local / 169.254.169.254 / IPv6 ULA**. Reached from
`validateRedirectUri()` (`:229`) on the unauthenticated-ish authorize flow.
Blind SSRF: internal port-scan, cloud metadata reachability, internal-service
interaction. Response not reflected (blind), still actionable.

Fix (Phase B): drop URL-as-client_id metadata fetch, or restrict to https +
public-IP allowlist with DNS-rebind protection and redirect disabled.

---

### AUTH-07 — Refresh-token rotation not atomic → single-use bypass / token duplication — **MED / Security**

`OAuthService::refreshToken()` `:99-154` performs lookup → expiry/client
checks → create access+refresh → delete old, with **no DB transaction or row
lock**. Concurrent requests with the same refresh token both pass the TOCTOU
checks and both mint valid token pairs (replay within the race window). No
"refresh-token reuse ⇒ revoke token family" detection (RFC 6819 §5.2.2.3).

Fix (Phase B): wrap in a transaction with `lockForUpdate()` on the refresh
row (or atomic conditional delete); on reuse of an already-rotated token,
revoke the entire token family for that user/client.

---

### AUTH-08 — Authorization-code consumption not atomic → code replay — **MED / Security**

`exchangeCode()` deletes the code (`OAuthService.php:91`) only after token
creation, no transaction/lock. Concurrent identical requests both pass
`->first()` before either deletes → multiple token pairs from one code
(RFC 6749 §10.5).

Fix (Phase B): atomic single-use (transaction + `lockForUpdate`, or
delete-returning guard) before issuing tokens.

---

### AUTH-09 — Sensitive OAuth params logged at info level — **LOW / Security**

`OAuthController.php:27-34` logs `client_id, redirect_uri, state, scope`;
`:110-114` logs `redirect_uri, state`. `state` can be a client CSRF token /
context. Compounds HYGIENE-01 (logs were VCS-tracked) and there is no log
redaction. Authorization `code` is correctly *not* logged (only `has_code`).

Fix (Phase B): drop or redact these; gate behind debug.

---

### AUTH-10 — 30-day access tokens, no revocation endpoint — **LOW / Security**

`config/urge.php:22-24`: `token_ttl` **and** `refresh_token_ttl` = 2592000
(30 days). A 30-day *access* token (not just refresh) hugely widens stolen-
bearer exposure. No RFC 7009 `/oauth/revoke` and no UI to revoke issued OAuth
tokens (only API keys are user-manageable). Long-lived bearer + no revocation
amplifies AUTH-01/03.

Fix (Phase B): shorten access TTL (≤1h) relying on refresh; add a revoke
endpoint + "active sessions" UI.

---

### AUTH-11 — Loopback redirect_uri accepted for unregistered clients — **LOW / Security**

`OAuthService::validateRedirectUri()` `:234-238`: if client unknown and
metadata unfetchable, **any** `redirect_uri` with host
`localhost|127.0.0.1|[::1]` is accepted (no registration at all). Dev
convenience that, in production, lets an attacker complete flows via a local
listener without registering a client.

Fix (Phase B): gate the loopback fallback behind `app()->environment('local')`.

---

### AUTH-12 — API-key activation relies on DB default; no scope check here — **LOW / Bug**

`ApiKeyService::generateKey()` (`:20-25`) never sets `is_active`; validity
(`ApiKey::isValid()`) requires `is_active && !expired`. Correctness depends
entirely on the migration default — verify in Sprint 2. API-key *prompt
scoping* enforcement across controllers is deferred to **Sprint 2**
(authorization).

---

## Dependency CVEs (HARNESS-01 follow-up)

`composer audit` now runnable (`COMPOSER_ALLOW_SUPERUSER=1`):

| ID | Package | Sev | CVE | Note |
|---|---|---|---|---|
| DEP-03 | `league/commonmark` >=2.3.0,<=2.8.1 | Med | CVE-2026-33347 | embed-extension `allowed_domains` bypass; only relevant if embed extension used — verify in Sprint 3/6 |

HARNESS-01 closed: `composer audit` works with the documented flag.

---

## Positives (no action)

- All codes/tokens/secrets SHA-256 hashed at rest; `Str::random(64)` (CSPRNG).
- `hash_equals()` used for PKCE and client-secret comparison (constant-time).
- PKCE **S256-only** enforced; `plain` rejected (`OAuthController.php:48`,
  `OAuthService.php:171`).
- Refresh token client-bound (`OAuthService.php:110`); scope **downscope-only**
  on refresh (`:116-125`); scope validated against config allowlist.
- CSRF exemption correctly limited to `oauth/token`,`oauth/register` (spec).
- GitHub `state` CSRF parameter validated (`OAuthGitHubController.php:45-48`).

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| AUTH-01 | High | Security | OAuthGitHubController.php:25-28,90-105; OAuthController.php:79-119 |
| AUTH-02 | High | Security | routes/web.php:67-68; bootstrap/app.php:22-25 |
| AUTH-03 | High | Security | OAuthGitHubController.php:67-86; User.php:5 |
| AUTH-04 | Med | Bug | OAuthGitHubController.php:55-86 |
| AUTH-05 | Med | Security | OAuthService.php:31,56-70 |
| AUTH-06 | Med | Security | OAuthService.php:194-213,229 |
| AUTH-07 | Med | Security | OAuthService.php:99-154 |
| AUTH-08 | Med | Security | OAuthService.php:40-97 |
| AUTH-09 | Low | Security | OAuthController.php:27-34,110-114 |
| AUTH-10 | Low | Security | config/urge.php:22-24 |
| AUTH-11 | Low | Security | OAuthService.php:234-238 |
| AUTH-12 | Low | Bug | ApiKeyService.php:20-25 |
| DEP-03 | Med | Security | composer audit (league/commonmark) |

Cross-cutting: AUTH-01 + AUTH-02 + AUTH-03 + AUTH-05 chain into full account/
MCP-session takeover and are the top Phase-B priority.

## Next

Sprint 2 — Authorization & Multi-Tenant Isolation (report-only). Pending user
review of this report.
