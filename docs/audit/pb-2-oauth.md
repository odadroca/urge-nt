# PB-2 — OAuth & GitHub identity (remediation)

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes 11 audit findings across OAuth 2.1 and GitHub login.
**Suite:** 450 → **465 passing** (15 new regression tests).

This is the second remediation sprint of Phase B. It implements Theme F
from `docs/audit/08-summary.md`: redirect_uri allowlist enforcement at
every code-issuing path, atomic single-use rotation, PKCE-required-when-
challenge-bound, removal of the SSRF metadata-fetch path, RFC 7009
revocation, shorter access TTL, throttling on the unauth OAuth endpoints,
and a provider-identity table that breaks the unverified-email ATO path.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **AUTH-01** | High | `OAuthController::handleAuthorize` now calls `validateRedirectUri()` on the POST path (the GET check was bypassable). `OAuthGitHubController::redirect` validates the inner URGE OAuth request **before** storing it in session; `callback`'s `resumeAuthorize()` re-validates defensively against session tampering. |
| **AUTH-02** | High | `throttle:20,1` on `/oauth/token` and `/oauth/revoke`; `throttle:5,1` on `/oauth/register`. Was: unthrottled. |
| **AUTH-03** | High | New `user_identities` table binds `(provider, provider_user_id) ↔ user_id`. The callback looks up by identity first (stable across email changes upstream). For new federated accounts it **requires a verified primary email**; refuses to silently link to a pre-existing local account by email — the user must explicitly link from settings. |
| **AUTH-04** | Med | GitHub callback wraps every `Http::` call in try/catch + `$resp->successful()` + key-presence checks. Failures redirect to `/` with an error, no 500s, no undefined-index warnings. |
| **AUTH-05** | Med | `OAuthService::exchangeCode` now requires a matching `code_verifier` whenever the code was bound to a `code_challenge`, regardless of client confidentiality. Public clients without a challenge are refused outright (no auth proof). |
| **AUTH-06** | Med | `fetchClientMetadata()` (the blind-SSRF surface that did `Http::get($clientId)` for URL-shaped client IDs) removed from `validateRedirectUri`. Test confirms zero outbound HTTP for unknown clients. |
| **AUTH-07** | Med | `refreshToken()` wrapped in `DB::transaction` with `lockForUpdate()` on the refresh row; old tokens deleted **before** issuing the new pair. Concurrent re-use of the same refresh token now yields one rotated pair, not two. |
| **AUTH-08** | Med | `exchangeCode()` wrapped in `DB::transaction` with `lockForUpdate()` on the authorization code row; the code is deleted before tokens are issued. Auth-code replay impossible. |
| **AUTH-09** | Low | `Log::info('OAuth authorize request', …)` and `Log::info('OAuth authorize redirect', …)` calls removed from `OAuthController` (they logged `client_id`/`redirect_uri`/`scope`/`state`). |
| **AUTH-10** | Low | `OAUTH_TOKEN_TTL` default lowered from `2592000` (30d) to `3600` (1h) in `config/urge.php` and `.env.example`. Refresh-token TTL unchanged. New `POST /oauth/revoke` endpoint per RFC 7009 §2.2 (accepts access **or** refresh token; revokes both halves of the grant; always 200 per spec). |
| **AUTH-11** | Low | `validateRedirectUri` loopback-fallback gated behind `!app()->environment('production')`. Existing dev/test flows still work; production refuses unregistered loopback clients. |

**Note (AUTH-12):** already closed in PB-1.

---

## Architecture

### New files

- `database/migrations/2026_05_20_110621_create_user_identities_table.php`
  — `(provider, provider_user_id)` unique; `email_verified` boolean for
  forensics.
- `app/Models/UserIdentity.php` — thin Eloquent model.
- `tests/Feature/OAuthPb2Test.php` — 15 regression tests.

### Modified files

- `app/Services/OAuthService.php`
  - `exchangeCode` + `refreshToken` wrapped in transactions with row locks.
  - `exchangeCode` enforces PKCE whenever the code was bound to a challenge.
  - `validateRedirectUri` no longer fetches client metadata over HTTP
    (SSRF surface removed); loopback fallback gated to non-production.
  - New `revokeToken($rawToken, $clientId)` — handles both access and
    refresh tokens; revokes the sibling token of the same grant.
- `app/Http/Controllers/OAuthController.php`
  - `handleAuthorize` re-validates redirect_uri allowlist on POST.
  - Sensitive `Log::info(...)` calls removed.
  - New `revoke()` method.
- `app/Http/Controllers/OAuthGitHubController.php`
  - `redirect()` validates the inner URGE OAuth request (client/redirect/
    scope/PKCE) **before** storing in session.
  - `callback()` rebuilt: identity-first lookup via `user_identities`;
    requires verified primary email for new federated accounts; refuses
    silent linking to existing local accounts by email; robust HTTP error
    handling.
  - `resumeAuthorize()` re-validates session-stored redirect_uri before
    issuing the URGE auth code.
- `routes/web.php` — `throttle:` middleware on `/oauth/token` (20/min),
  `/oauth/register` (5/min), `/oauth/revoke` (20/min); new revoke route.
- `config/urge.php` — `oauth.token_ttl` default `3600`.
- `.env.example` — `OAUTH_TOKEN_TTL=3600` + comment.

### Decision points

- **Identity-first GitHub login.** A `(provider, sub)` pair is the stable
  binding. Email is informational (and stored) but never the primary
  lookup — upstream email churn / privacy settings can't break the
  binding. Pre-existing local accounts are **not** auto-linked: that
  was the ATO surface. Out-of-band "link GitHub from settings" is a
  follow-up feature, intentionally out of PB-2 scope.
- **Loopback fallback gated by environment.** Two existing tests
  (`test_full_oauth_flow_with_pkce`, `test_existing_url_based_client_id_still_works`)
  use loopback URL-shaped client IDs. `app()->environment(['local','testing'])`
  covers both dev and test — only `production` flips the fallback off,
  matching the doc commentary in the audit.
- **Revocation responds 200 even on miss.** RFC 7009 §2.2 explicitly
  requires this; covered by `test_revoke_endpoint_responds_200_for_unknown_token`.
- **PKCE for confidential clients.** Spec considers PKCE optional when
  a client_secret is present, but our code-binding made empty-challenge
  fields exchangeable without a verifier when combined with a
  confidential client. The audit reasoning: if the *code* was bound to
  a challenge, the *exchange* must prove the challenge regardless of
  client type. PB-2 enforces that. Public clients without any challenge
  are rejected (no auth proof at all).

---

## Test coverage added

`tests/Feature/OAuthPb2Test.php` (15 tests):

- **AUTH-01:** consent POST with off-allowlist `redirect_uri` redirects
  to `/`, no code created.
- **AUTH-02:** `/oauth/token` returns 429 after 20 attempts; same for
  `/oauth/register` after 5.
- **AUTH-03:** unverified email refused; existing-local-account silent
  link refused; new verified account creates identity + user; existing
  identity binding wins regardless of upstream email.
- **AUTH-04:** upstream 503 → user-facing redirect, no 500.
- **AUTH-05:** exchange with empty `code_verifier` against a
  challenge-bound code returns null.
- **AUTH-06:** `Http::assertSentCount(0)` confirms no outbound HTTP for
  unknown URL-shaped client IDs.
- **AUTH-07:** refresh-token replay returns null after first rotation.
- **AUTH-08:** authorization-code replay returns null after first
  exchange.
- **AUTH-09:** `Log::shouldNotHaveReceived('info')` during authorize.
- **AUTH-10:** revoke endpoint kills both access + refresh tokens;
  MCP returns 401 with the revoked token; revoke on unknown token →
  200 per RFC.

---

## Verification

```
$ php artisan test
Tests:    465 passed (1253 assertions)
Duration: ~10s
```

Zero regressions in the existing OAuth flow tests (`OAuthFlowTest`'s 25
scenarios continue to pass). PB-1 regression tests (33) also unchanged.

---

## Next

PB-3 — Template engine deny-by-default + DoS budgets (Themes B, D).
Closes TPL-01..09 + AUTH-06 reuse (shared SSRF guard if applicable).
