# Sprint 8 — Synthesis & Prioritized Backlog

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Phase A complete. **This is the Phase B hand-off artifact.**
**Suite at audit close:** 417 passing (Sprint 0 baseline; unchanged
through audit — no code touched except `.gitignore` HYGIENE-01 fix).

Source reports (1962 lines total):
`docs/audit/{00..07}-*.md` — read each for evidence and reasoning;
this document is the consolidated, deduplicated backlog and the
proposed Phase B execution plan.

---

## 1. Headline Findings

**77 findings total** across 8 audit sprints (Phase A), grouped here by
severity (deduplicated; closed items excluded). Crit/High items below
chain into the three exploit narratives in §3.

| Severity | Count | Sprints |
|---|---|---|
| **Critical** | **4** | Sprint 2 ×3, Sprint 5 ×1 |
| **High** | **18** | Sprints 1, 2, 3, 4, 5, 6, 7 |
| Medium | 32 | all sprints |
| Low | 23 | all sprints |
| Closed / informational | 3 | HARNESS-01, HYGIENE-01, INFRA-12 |

The four **CRIT** findings share **one root cause**: route-model-bound
resources reach controllers / MCP tool handlers with no authorization
layer (see Theme A in §2).

---

## 2. Cross-Cutting Themes

### Theme A — Authorization is per-method, not per-resource

**Findings:** AUTHZ-01, AUTHZ-02, AUTHZ-03, AUTHZ-04, AUTHZ-05,
AUTHZ-06, AUTHZ-07, AUTHZ-08, MCP-01, MCP-02, MCP-03, MCP-04, MCP-08,
DEAD-03.

Result/Pipeline/Channel/Evaluation route bindings deliver
DB-instantiated models straight to controllers and MCP tool handlers
with no Policy / no scope check. Where checks exist they are hand-rolled
per method and inconsistently applied (some methods of `ResultController`
check visibility on download but not on show/update/destroy). API-key
prompt scoping is enforced in **4 of ~12** prompt-addressing endpoints;
in MCP, API keys bypass `mcp:*` scopes entirely **and** the pipeline-
mutation tools omit ownership checks → least-privilege key reaches admin
tools cross-tenant.

**Phase B direction:** introduce Laravel Policies for Result, Pipeline,
PipelineChannel, Evaluation, Prompt; share them between REST and MCP via
an `AuthorizationService`. Apply `role:admin` middleware **in addition
to** controller `isAdmin()` checks. Promote `authorizePromptAccess` to
centralized API-key scope enforcement.

### Theme B — "Fail-open on null user" / global slug fallback

**Findings:** TPL-01, TPL-02, MCP-06, MCP-07, AUTHZ-06, DEAD-03,
AUTH-11.

`TemplateEngine::findPromptBySlug`, `McpToolHandler::resolvePrompt`, the
`urge://prompts` resource, `ResolvesPrompts::resolvePrompt`, the legacy
`/prompts/{slug}` redirect, and `OAuthService::validateRedirectUri` all
fall back to **global** lookups when their user context is null. Reached
unauth via `/share/{token}` (TPL-01), reached cross-tenant via
`PipelineService::run` (TPL-02 → AUTHZ-04 chain), reached via the stdio
MCP command's `User::first()` (MCP-10).

**Phase B direction:** make user context mandatory on every render /
resolve / lookup; delete global fallbacks; fail closed.

### Theme C — Credential exfil chain

**Findings:** LLM-01 + LLM-02 + LLM-03 + LLM-11 + LLM-04 (and
amplified by AUTHZ-04 / MCP-02 reach).

Provider `endpoint` is admin-set and unvalidated → SSRF carrying the
configured bearer; Gemini puts the key in the URL → `$e->getMessage()`
includes the URL; every driver echoes `$response->body()` verbatim as
the error; the error is persisted into `results.error_message` and
echoed to the SPA `console.error`. A misconfigured/attacker-controlled
endpoint can therefore exfiltrate API keys to the URGE DB and SPA
console in one failing call.

**Phase B direction:** endpoint allowlist + scheme/host SSRF guard;
Gemini key via header; redact upstream URLs from error messages; cap
echoed-body size; central response sanitizer.

### Theme D — DoS amplification chain

**Findings:** TPL-04 + LLM-07 + LLM-06 + AUTH-02 + TPL-06.

Include expansion has only a per-path depth limit → sibling repetition
multiplies (Nⁿ); rendered prompt is dispatched to LLM drivers with
120-300s timeouts and no size cap; Sanctum/OAuth callers are
unthrottled on LLM-dispatching routes; `/share/{token}` and
`/oauth/{token,register}` have no throttle. One authenticated request
can OOM/block a worker for 5 minutes; one unauth share-link hit can
trigger the same render storm.

**Phase B direction:** global expansion budget + output-size cap;
queue LLM dispatch jobs; per-user throttle on every LLM-firing route
across all auth modes; throttle the unauth share route.

### Theme E — No defense-in-depth at the HTTP layer

**Findings:** INFRA-01, INFRA-02, INFRA-03, INFRA-04, INFRA-05,
MCP-05, INFRA-09.

No security headers (CSP, HSTS, X-Frame-Options, X-Content-Type-Options,
Referrer-Policy); `/docs` loads Scalar from CDN with no SRI; CORS config
declares wildcard origins with credentials; MCP Origin allowlist
degrades to "any HTTPS"; `APP_DEBUG=true` in `.env.example`; public
OpenAPI with hardcoded production domain; sessions plaintext + cookies
unsecured. A single XSS or CDN compromise has nothing between attacker
and the full corpus.

**Phase B direction:** `SecurityHeaders` middleware (strict CSP for
`/docs`); pin/SRI Scalar (or self-host); env-driven CORS allowlist;
flip `.env.example` defaults; templatize OpenAPI server URL.

### Theme F — Auth/OAuth boundary issues

**Findings:** AUTH-01..08 (Sprint 1).

`handleAuthorize` POST and the GitHub callback both issue authorization
codes to unvalidated `redirect_uri`; `/oauth/token` and `/oauth/register`
have no throttle; open client registration permits attacker-controlled
confidential clients; GitHub auto-provisions accounts on an unverified
email (ATO); confidential clients bypass PKCE even when a challenge was
bound; refresh-token rotation and code consumption are non-atomic
(replay race); `client_id`-as-URL metadata fetch is a blind SSRF.

**Phase B direction:** centralize redirect_uri validation at every
issuance path; throttle the OAuth endpoints; require verified GitHub
email and store provider-identity (provider+sub), not email-match;
require code_verifier when a challenge was bound; wrap rotation/
consumption in transactions with `lockForUpdate`.

### Theme G — Drift / no enforcement loop

**Findings:** DOC-02, DOC-03, DOC-04, DOC-05, DOC-06, DOC-07, DOC-09,
INFRA-05, INFRA-07.

Test counts wrong in 3 docs (and they disagree with each other); tool
counts wrong in 5 docs; OpenAPI documents 20 of 93 routes; multiple
docs hardcode the maintainer's domain; plan docs not marked DELIVERED;
no CI to catch any of it.

**Phase B direction:** generate counts and tool list from code at build
time; templatize domains; add a `.github/workflows/ci.yml` running
tests + audit + a "no-drift" doc check.

### Theme H — Dead Livewire-era residue

**Findings:** DEAD-01, DEAD-02, DEAD-04, DEAD-05 (and AUTHZ-07 dead
alias).

`AiAssistantService` has zero callers; `api.auth` and `role:` middleware
aliases unused; `GET /api/v1/mcp` exists only to return 405. The legacy
`/prompts/{slug}` redirect (DEAD-03) is also dead-end *and* a security
hole (Theme A).

**Phase B direction:** delete or wire; track in a "post-migration
cleanup" sprint that runs **after** security fixes ship (deletion order
matters: DEAD-03 is fixed in the Theme-A sprint, then routes removed).

### Theme I — Missing governance & operational hygiene

**Findings:** DOC-01 (no LICENSE despite MIT claim), DOC-08 (no
SECURITY.md/CONTRIBUTING.md/CHANGELOG.md), INFRA-08 (deploy script
gaps), INFRA-10 (env knobs undocumented), INFRA-11 (/up leaks
framework), AUTH-09 (sensitive OAuth params logged), AUTH-10 (30-day
access tokens, no revoke endpoint).

**Phase B direction:** ship LICENSE + SECURITY.md before any external
disclosure; refine deploy script with backup/rollback; document every
env knob; reduce access-token TTL and add RFC 7009 revocation.

---

## 3. Exploit Chains (the narrative)

These are the highest-impact paths an attacker walks with today's
codebase. Each cites the findings combined.

**Chain 1 — Account takeover + cross-tenant exfil (highest impact)**

`AUTH-02` (open client registration, unthrottled)
→ attacker self-issues confidential client with known `client_secret`
→ `AUTH-01` (consent POST / GitHub callback skip `validateRedirectUri`)
→ victim's authorization code lands at attacker URL
→ attacker exchanges code (PKCE bypassed by `AUTH-05` if confidential)
→ attacker holds 30-day access token (`AUTH-10`, no revoke endpoint)
→ `AUTHZ-01..03` IDORs expose every Result / Pipeline / Channel
→ DB content read includes prompt corpus + LLM responses
(`LLM-05` plaintext at rest).

**Chain 2 — Public exfiltration of any tenant's private fragments**

`TPL-01` (public `/share/{token}` renders includes with null user)
→ `TPL-02` (`findPromptBySlug` global fallback on null user)
→ a `{{>victim-private-fragment-slug}}` in a shared collection prints
verbatim on the unauthenticated share page
→ attacker either crafts their own collection or exploits a slug
collision in someone else's collection
→ `TPL-04` billion-laughs amplifies the page render into a DoS at the
same time (`TPL-06` no throttle).

**Chain 3 — LLM credential exfil into the DB**

Admin (compromised, or insider, or via AUTHZ-07 regression) sets a
provider `endpoint` (`LLM-01`) to an internal request inspector
→ next driver call attaches `Authorization: Bearer <provider-api-key>`
→ inspector returns a body echoing the headers
→ `LLM-03` writes that body verbatim into `results.error_message`
→ `LLM-05` plaintext storage retains it
→ `LLM-11` SPA `console.error` displays it on next failure UI
→ `MCP-12` `tools/call` returns it via MCP as well
→ same chain via `LLM-02` for Gemini without needing SSRF: any
connection error message includes the URL with the key.

**Chain 4 — Cross-tenant pipeline execution + private-fragment
injection into LLM calls**

`AUTHZ-04`/`MCP-02` (runPipeline ignores prompt visibility)
→ attacker runs any active pipeline against any user's prompt
→ `TPL-02` (`PipelineService::run` renders with null user)
→ includes resolve cross-tenant, the rendered output (carrying private
fragment content) is sent to the configured LLM provider and persisted
as a Result on the attacker's tenant
→ `LLM-06` (no rate limit on session/OAuth LLM-dispatch routes) enables
amplification.

---

## 4. Master Backlog

Schema unchanged from per-sprint reports
(`ID | Sev | Type | Evidence | Recommended fix`). Closed items omitted.

**CRIT (4):**

| ID | Area | Evidence | Theme |
|---|---|---|---|
| AUTHZ-01 | REST Result endpoints IDOR | `ResultController.php:103-149` | A |
| AUTHZ-02 | REST Evaluation IDOR + LLM spend | `EvaluationController.php:14-49` | A, C |
| AUTHZ-03 | REST Pipeline global access | `PipelineController.php:16-163` | A |
| MCP-01 | MCP pipeline-mutation no owner check | `McpToolHandler.php:1725-1890` | A |

**HIGH (18):**

| ID | Area | Theme |
|---|---|---|
| AUTH-01 | OAuth redirect_uri allowlist bypass (GH + consent POST) | F |
| AUTH-02 | OAuth token/register unthrottled + open registration | F, D |
| AUTH-03 | GitHub unverified-email ATO | F |
| AUTHZ-04 | runPipeline ignores prompt visibility | A, B |
| AUTHZ-05 | API-key scoping enforced on 4 of 12 endpoints | A |
| TPL-01 | Public share page null-user include disclosure | B |
| TPL-02 | findPromptBySlug global fallback everywhere | B |
| TPL-04 | Include billion-laughs (no expansion budget) | D |
| LLM-01 | Provider endpoint SSRF with bearer attached | C |
| LLM-02 | Gemini key in URL leaks via errors | C |
| LLM-03 | Upstream body echoed verbatim as error | C |
| MCP-02 | run_pipeline ignores pipeline ownership | A, B |
| INFRA-01 | /docs loads Scalar w/o SRI/pin | E |
| INFRA-02 | No security headers | E |
| INFRA-03 | CORS wildcards + credentials | E |
| INFRA-04 | APP_DEBUG=true default | E |
| INFRA-05 | Public OpenAPI + hardcoded domain | E, G |
| DOC-01 | LICENSE missing despite MIT claim | I |

**MED (32):**

OAuth: AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, DEP-03 — F.
Authz: AUTHZ-06, AUTHZ-07, AUTHZ-08 — A.
Template: TPL-03, TPL-05, TPL-06 — B, D.
LLM: LLM-04, LLM-05, LLM-06, LLM-07 — C, D.
MCP: MCP-03, MCP-04, MCP-05, MCP-06, MCP-07, MCP-08, MCP-10 — A, B.
Infra: INFRA-06, INFRA-07, INFRA-08, INFRA-09 — E, G.
Docs/dead: DEP-01, DEP-02, DOC-02..05, DOC-07, DOC-08, DEAD-01, DEAD-02, DEAD-03 — G, H, I.

(See per-sprint reports for full evidence rows.)

**LOW (23):** AUTH-09, AUTH-10, AUTH-11, AUTH-12, AUTHZ-09, AUTHZ-10,
TPL-07, TPL-08, TPL-09, LLM-08, LLM-09, LLM-10, LLM-11, MCP-09, MCP-11,
MCP-12, MCP-13, MCP-14, INFRA-10, INFRA-11, DOC-06, DOC-09, DEAD-04, DEAD-05.

---

## 5. Phase B — Proposed Sprint Sequence

Sequenced **highest-impact-first, but grouped to minimize churn** — a
fix in one theme often needs the same test scaffolding as its
neighbors. **Aggressive improvement** appetite (per user preference)
means each PB-sprint also lands the architectural cleanup, not just the
patch.

Suite must stay ≥ 417 green at the close of every PB-sprint; new
regression tests are listed per sprint in §6.

### PB-1 — Authorization layer (Theme A) — *Crit cluster*

**Lands:** AUTHZ-01..09, MCP-01..04, MCP-08, DEAD-03 (visibility), AUTH-12.

Introduce Laravel **Policies** for `Result`, `Pipeline`,
`PipelineChannel`, `Evaluation`, `Prompt`. Share between REST + MCP via
a single `AuthorizationService`. Promote `authorizePromptAccess` to a
shared API-key scope enforcer applied at the binding layer (form-request
or middleware) on every prompt-addressing route. Add `role:admin`
middleware to admin route groups *in addition to* controller checks.
Remove the legacy `/prompts/{slug}` redirect (or fix its visibility).

**Improvement:** abolish optional-`$user` signatures across the codebase
(`ResolvesPrompts`, `TemplateEngine::render`, `McpToolHandler::
resolvePrompt`, `readResource`); fail closed.

### PB-2 — OAuth & GitHub identity (Theme F)

**Lands:** AUTH-01..11.

Enforce `validateRedirectUri` on every code-issuance path; throttle
`/oauth/token` and `/oauth/register`; require GitHub email
`verified=true` and persist a `provider_identity` (provider+sub) table
instead of matching on email; require code_verifier whenever a challenge
was bound; wrap refresh-token rotation and code consumption in
transactions with `lockForUpdate`; drop URL-as-client_id metadata fetch
(or scheme/host-allowlist it); add RFC 7009 `/oauth/revoke`; shorten
access TTL.

**Improvement:** either replace home-rolled OAuth with Laravel Passport
or add an end-to-end test suite covering RFC 6749/6819 compliance.

### PB-3 — Template engine deny-by-default + DoS budgets (Themes B, D)

**Lands:** TPL-01..09, AUTH-06 (SSRF guard reused).

Remove `findPromptBySlug` global fallback; require user on every render
path including `PipelineService::run` / `resolveSystemPrompt`. Introduce
global expansion-count + total-output-size budget across the whole
render. Reject non-scalar variable values with 422. Namespace
include-derived metadata so fragment defaults can't leak into parent
scope. Throttle `/share/{token}`; reject `expiresIn=default` →
null; add UI revocation. Visited-set in collection render recursion;
strict-mode policy per surface.

**Improvement:** stream rendering so size cap can short-circuit; document
the budget knobs in `.env.example`.

### PB-4 — LLM driver hygiene (Themes C, D)

**Lands:** LLM-01..11, AUTH-06 (shared SSRF guard), LLM amplification
of TPL-04.

Endpoint allowlist with scheme/host SSRF guard (shared with AUTH-06);
Gemini API key via `x-goog-api-key` header; redact request URLs from
exception messages; cap echoed-body size; `encrypted` cast on
`response_text`/`rendered_content`/`error_message`/`variables_used`;
per-user throttle on every LLM-dispatching route regardless of credential
type; cap rendered-prompt size pre-dispatch; reject null endpoint for
ollama; document `CURL_SSL_VERIFY`; reduce SPA console-error to
`err.message + correlation-id` in production; tree-shake `console.*` in
prod build.

**Improvement:** queue LLM dispatch as a job (no 5min sync wait); central
"secret-shaped-string" detector at write time.

### PB-5 — Web transport & deploy hardening + CI (Theme E)

**Lands:** INFRA-01..11, MCP-05 (Origin tightening).

`SecurityHeaders` middleware (strict CSP for `/docs`, baseline for the
SPA); SRI + version-pin Scalar (or self-host); env-driven CORS
allowlist; `APP_DEBUG=false` and `SESSION_ENCRYPT=true` /
`SESSION_SECURE_COOKIE=true` in `.env.example`; templatize
`public/openapi.json` server URL or gate behind auth; un-comment
`/public/build` in `.gitignore` and untrack bundles; add
`.github/workflows/ci.yml` running tests + lint (`pint`) + builds +
`composer audit` + `npm audit`; deploy script: pre-deploy DB backup +
atomic symlink swap + post-deploy health check + rollback.

**Improvement:** SAST in CI (`larastan`/`psalm`); security-headers
contract test; dependabot config.

### PB-6 — Documentation regeneration + dead-code removal (Themes G, H)

**Lands:** DOC-01..09, DEAD-01, DEAD-02, DEAD-04, DEAD-05; closes
DOC-DRIFT-01/02/03.

Commit LICENSE (MIT to match the README claim); add SECURITY.md (vuln
disclosure path), CONTRIBUTING.md, CHANGELOG.md. CI step that derives
tool list and test count from code → fails the build on drift. Replace
hardcoded domains with `{appUrl}`/`{your-urge-instance}` placeholders
across `openapi.json`, `claude-skill.md`. Mark plan docs DELIVERED or
move to `docs/legacy/`. Delete `AiAssistantService` (or wire it).
Remove `api.auth` and `role:` aliases if not adopted in PB-1.
Remove `GET /api/v1/mcp` 405-only route. Decide OpenAPI policy:
regenerate to parity (preferred) or scope spec to what's intentionally
exposed.

**Improvement:** doc-from-code generator (tool list, env knobs, test
count) as a CI artifact.

### PB-7 — Dependency upgrades

**Lands:** DEP-01, DEP-02, DEP-03.

`npm audit fix` → re-run build + tests; confirm vite 7 + postcss bumps
clean. `composer why league/commonmark` → identify origin (likely
transitive via Symfony) and bump; verify embed extension is unused
(Sprint 3 noted no app-code reachability).

**Improvement:** add a CI dep-audit gate that fails on new High+.

### PB-8 — Verification & closure

For every Crit/High in PB-1..7, re-run the audit's repro (or its test
equivalent) against the fixed code. Add the missing security tests
(§6). Confirm 417 → 417+N green. Update `docs/audit/00-baseline.md`
with the closing-state facts (final test count, final tool count if
changed, npm/composer audit clean). Tag the closing commit.

---

## 6. Regression-Test Gap List

Tests that **should exist** to keep the audit closed; missing today.
Group by PB-sprint owner.

**PB-1 (Authorization):**
- Cross-user `GET/PATCH/DELETE /results/{id}` returns 404 not 200 (AUTHZ-01).
- `POST /results/{id}/evaluate` cross-user → 404 (AUTHZ-02).
- Cross-user `GET/PATCH/DELETE /pipelines/{slug}` + channel routes → 403 (AUTHZ-03).
- `runPipeline` against non-visible prompt → 404 (AUTHZ-04 + MCP-02).
- Prompt-scoped API key blocked on Version/Branch/Render/Result/Eval/Graph/Pipeline/Collection (AUTHZ-05).
- `role:admin` middleware applied + controller fallback (AUTHZ-07).
- Legacy `/prompts/{slug}` respects `visibleTo` (DEAD-03).
- MCP `update_pipeline`/`delete_pipeline`/`*_channel` reject non-owner (MCP-01).
- MCP `delete_result`/`update_result` require ownership not just visibility (MCP-03).
- MCP `pin_version`/`archive_version` reject non-owner (MCP-04).
- API key cannot call admin MCP tools (or scope semantics documented) (MCP-08).

**PB-2 (OAuth):**
- Consent POST with mismatched `redirect_uri` → 400 (AUTH-01).
- GitHub callback with attacker `redirect_uri` in session → 400 (AUTH-01).
- `/oauth/token` and `/oauth/register` rate-limit assertions (AUTH-02).
- GitHub login refuses unverified email; existing account requires explicit linking (AUTH-03).
- GitHub callback handles HTTP failure paths (AUTH-04).
- Confidential client with PKCE-challenge code must present `code_verifier` (AUTH-05).
- `client_id`-as-URL fetch blocks RFC1918/loopback/metadata (AUTH-06).
- Concurrent refresh-token races: only one token pair issued (AUTH-07).
- Concurrent auth-code consumption: only one token pair issued (AUTH-08).
- Loopback redirect blocked outside `local` env (AUTH-11).

**PB-3 (Template/DoS):**
- Public share page rejects cross-tenant include (TPL-01/TPL-02).
- Pipeline render uses owner's user (no global fallback) (TPL-02).
- Array variable value → 422 (TPL-03).
- Bombing test: N⁴ expansion attempts blocked by global budget (TPL-04).
- Fragment-supplied `default` does not leak into parent variable (TPL-05).
- `/share/{token}` rate-limit assertion (TPL-06).
- Expiry default is not `null` (TPL-06).
- Visible vs missing include give the same wire response (TPL-08).

**PB-4 (LLM):**
- Provider with RFC1918/loopback `endpoint` blocked at create + at dispatch (LLM-01).
- Gemini error path does not include API key (LLM-02).
- Upstream-body echo path is capped + sanitized (LLM-03).
- Result content columns round-trip via `encrypted` cast (LLM-05).
- Per-user throttle assertion on `/providers/{id}/test`, `/prompts/{slug}/run`, `/prompts/{slug}/run-pipeline` for Sanctum + OAuth (LLM-06).
- Rendered-prompt size cap before dispatch (LLM-07).

**PB-5 (Infra):**
- `/docs` HTML contains `integrity=` attribute on Scalar script (INFRA-01).
- `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security`, `Referrer-Policy` present on `/`, `/app/*`, `/api/v1/*` responses (INFRA-02).
- CORS preflight from a non-allowlisted origin → 403/no headers (INFRA-03).
- `APP_DEBUG` is `false` when `APP_ENV=production` (INFRA-04 deploy guard).
- Session cookie has `Secure` + `HttpOnly` + `SameSite=lax` (INFRA-09).

---

## 7. Recon Corrections (consolidated)

Phase A produced measured values that supersede the Sprint 0 recon
estimates and the doc-claimed values. Use these in Phase B:

| Metric | Pre-audit claim(s) | Measured |
|---|---|---|
| Tests passing | 386 (README) / 410 (CLAUDE.md ×2) | **417** |
| MCP tools registered | 29 (5 docs) | **31** |
| Migrations | 48 (recon) | **38** |
| Top-level services | 20 (recon) | **12** |
| OAuth refresh tokens | "status unclear" (doc-agent) | **delivered** (migration present) |
| `docs/legacy/` file count | 24 (recon) | **9** |
| `PipelinesPage` | "minimal stub" (recon) | **intentional 11-line wrapper** |
| `AiAssistantService` callers | "diff summary + improvements" (recon) | **zero callers** (DEAD-01) |
| Share token | "predictable" (recon assumption) | **256-bit CSPRNG** (refuted) |
| `composer audit` | blocked (Sprint 0) | runs with `COMPOSER_ALLOW_SUPERUSER=1`; 1 advisory (DEP-03) |

---

## 8. Scope Notes & Out-of-Audit Items

**Confirmed not exploited (closed):**
- HYGIENE-01: `storage/logs/laravel.log` was VCS-tracked; historical
  content scanned (216 lines) — **no secrets/tokens/passwords/bearers**
  in history. Risk was real, leak did not materialize.
- HARNESS-01: `composer audit` runs with the documented superuser flag;
  no further action.
- DOC-DRIFT-03: refresh tokens are implemented (Sprint 0 confirmed
  migration + Sprint 1 confirmed `OAuthService::refreshToken`); plan
  doc just needs DELIVERED marker (rolled into DOC-07).

**Not in scope for this audit:**
- Live exploitation against any hosted instance.
- LLM-output safety (prompt-injection content-level) beyond
  TPL-05's metadata-merge attack.
- Performance/scaling beyond DoS-relevant findings (LLM-07, TPL-04).
- Locale/I18n.
- Mobile SPA / accessibility.
- Production-DB encryption-at-rest (operator-controlled; flagged in
  LLM-05 as the operator's responsibility, not app-layer).

---

## 9. Closing Stance

Phase A surfaced **77 findings** including **4 CRIT and 18 HIGH**. The
CRITs collapse to one architectural fix (Theme A — a Policy layer for
route-bound resources). The HIGHs are largely explained by four themes
(B, C, D, F). The remaining body is hygiene that piles up without CI
(Theme E + G + I).

**Gating Phase B:** user reviews this synthesis. On approval, the
sequence in §5 begins with PB-1; PB-1 alone closes the 4 CRITs and 6
HIGHs.

PB-8 verification is the close-out gate: every Crit/High has a passing
regression test (§6), `php artisan test` ≥ 417 green, `npm audit` and
`composer audit` clean, the audit baseline (`00-baseline.md`) refreshed
to the post-fix state.

---

End of Phase A.
