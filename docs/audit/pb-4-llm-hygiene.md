# PB-4 — LLM driver hygiene

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes the 11 LLM-driver audit findings (LLM-01..11)
plus the AUTHZ-10 endpoint-disclosure leftover from Sprint 2.
**Suite:** 477 → **491 passing** (14 new regression tests).

This is the fourth remediation sprint of Phase B. It implements Theme C
from `docs/audit/08-summary.md`: cut the "provider config → credential
exfil into DB and SPA" chain. The chain's three independent pieces
(SSRF-with-bearer, Gemini-key-in-URL, upstream-body-echoed-as-error)
are all closed; results are now encrypted at rest; LLM-dispatching
endpoints are throttled across all credential types; production SPA
builds drop `console.*` to prevent dev-tools dumping of Axios errors.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **LLM-01** | High | New `App\Services\UrlSafetyService::assertSafe()` blocks loopback, RFC1918, link-local, CGNAT, ULA, and cloud-metadata literals (AWS/GCP). Applied at provider create/update (controller validation) AND at dispatch time (defense-in-depth — provider rows seeded directly by future imports still get screened). HTTP redirects are disabled on every driver. |
| **LLM-02** | High | Gemini driver now sends the API key via `x-goog-api-key` header (was: `?key=…` in URL). Exception messages no longer carry the URL — `DriverErrorSanitizer::generic()` returns only the exception class name, never `$e->getMessage()`. |
| **LLM-03** | High | `DriverErrorSanitizer::trim()` redacts URLs and key-shaped strings from upstream-error text, caps at 1 KiB. Drivers no longer fall back to `$response->body()` on error — they emit a generic per-provider failure message when the structured `error.message` field is absent. |
| **LLM-04** | Med | Every `catch (\Throwable $e)` in every driver routes `$e` through `DriverErrorSanitizer::generic()` → class name only. No more `$e->getMessage()` reflection. |
| **LLM-05** | Med | `Result` model now `encrypted` casts on `response_text`, `rendered_content`, `error_message`, and `encrypted:array` on `variables_used`. New `response_hash` (sha256) column populated by a `saving` hook so `ImportV1Command` dedup still works against the encrypted column. Migration `2026_05_20_120000_add_response_hash_to_results`. |
| **LLM-06** | Med | New `throttle:30,1` middleware on the three LLM-firing routes: `prompts/{u}/{s}/run`, `prompts/{u}/{s}/run-pipeline`, `results/{id}/evaluate`. Was: only API-key callers were throttled (`ApiKeyAuthentication`); Sanctum/OAuth callers were unbounded. |
| **LLM-07** | Med | New `urge.max_prompt_bytes` (default 1 MiB) enforced inside `LlmDispatchService::{dispatch,dispatchWithSystem}` before driver construction. Bounds workload-amplification when TPL-04's include budget is set high. |
| **LLM-08** | Low | `LlmDispatchService::resolveDriver` throws if an `ollama` provider has no endpoint (was: silently defaulted to `http://localhost:11434`). Controller validates the same on create/update. |
| **LLM-09** | Low | `CURL_SSL_VERIFY=true` documented in `.env.example` (it was respected in code but absent from the operator-visible env template). Also added template/LLM safety knob comments. |
| **LLM-10** | Low | `LlmProvider.settings` cast unchanged but `endpoint` is now `url`-validated at controller level. Settings sprawl is out of scope (the audit's LOW recommendation); the more impactful gap was the endpoint, which is now closed. |
| **LLM-11** | Low | `vite.config.js` now `esbuild.drop = ['console','debugger']` in production builds. All 27 `console.error('… failed:', err)` SPA call sites become no-ops in prod bundles, so full Axios errors don't end up in user devtools or screenshots. |
| **AUTHZ-10** | Low | `LlmProviderController::index` still returns `endpoint` to non-admins for SPA "pick a provider" UX. PB-4 doesn't change this — the audit graded it LOW and removing it would break the SPA's provider picker. Deferred to PB-6 doc/UX cleanup or accepted as design. |

---

## Architecture

### New files

- `app/Services/UrlSafetyService.php` — IP-literal SSRF guard. Pragmatic
  scope: refuses literal-IP RFC1918/loopback/link-local/ULA/CGNAT and
  cloud-metadata literals; refuses non-`https` for public providers.
  Does not do live DNS-rebind protection (that requires hooking Guzzle's
  curl handle to lock the resolved IP — out of PB-4 scope; recommend
  egress firewall at the network layer for full coverage).
- `app/Services/LlmProviders/DriverErrorSanitizer.php` — `trim()` and
  `generic()` helpers used by every driver.
- `database/migrations/2026_05_20_120000_add_response_hash_to_results.php`
  — adds `response_hash` CHAR(64) + index.
- `tests/Feature/LlmPb4Test.php` — 14 regression tests.

### Modified files

- `app/Services/LlmDispatchService.php` — pre-dispatch prompt-size cap,
  endpoint SSRF guard, Ollama-requires-endpoint check.
- `app/Services/LlmProviders/{OpenAi,Anthropic,Mistral,Gemini,Ollama,
  OpenRouter}Driver.php` — sanitized error messages, `allow_redirects:
  false`, Gemini key via header.
- `app/Models/Result.php` — encrypted casts on four columns;
  `response_hash` boot hook.
- `app/Console/Commands/ImportV1Command.php` — dedup via `response_hash`
  (was: `where('response_text', $value)` — now matches ciphertext that
  changes each write).
- `app/Http/Controllers/Api/LlmProviderController.php` — `endpoint`
  validated as `url` + SSRF-asserted on create/update; Ollama-requires-
  endpoint check.
- `routes/api.php` — LLM-firing routes grouped under `throttle:30,1`.
- `config/urge.php` — new `max_prompt_bytes` knob.
- `.env.example` — `CURL_SSL_VERIFY=true` documented; template/LLM
  knob comments added.
- `vite.config.js` — production `esbuild.drop` for console+debugger.
- `tests/Feature/Api/ResultApiTest.php`,
  `tests/Feature/McpToolHandlerTest.php` — three `assertDatabaseHas
  ('results', ['response_text' => …])` calls migrated to query
  `response_hash` (the encrypted column would never match plaintext).

### Decision points

- **`encrypted` cast strategy vs. application-level crypto.** Laravel's
  `encrypted` cast uses the same `APP_KEY` that signs sessions and
  encrypts other model fields. Compromise of `APP_KEY` decrypts
  everything — same posture as existing encrypted columns (`api_key`).
  This is the right tier for PB-4; per-record DEK + KMS is a feature,
  not a security baseline.
- **`response_hash` for dedup.** Non-deterministic IV in Laravel
  encryption means equality queries against the encrypted column will
  never match. Storing a sha256 of the plaintext alongside is the
  standard pattern; the hash is non-sensitive (free-text responses
  aren't reversible from sha256 in practice). The hook fires on
  `saving` whenever `response_text` is dirty, so backfill is implicit
  for new data; a data-migration command for existing prod data is
  out of scope (this audit was on a clean repo).
- **Throttle limit 30/min on LLM routes.** Generous enough for
  interactive UI flows (multiple chained runs in a session) but firmly
  caps cross-tenant LLM-spend abuse via AUTHZ-04 (already closed in
  PB-1) or runaway scripts.
- **SSRF guard is IP-literal only.** A determined attacker can still
  resolve a hostname to a public IP at validation time and rebind to a
  private IP at fetch time. Full mitigation requires Guzzle's
  `on_stats` callback to pin the resolved IP per request. Documented
  in `UrlSafetyService.php` for future hardening; for PB-4 the IP
  literal coverage closes the obvious vectors (operator pasting a
  metadata URL, an attacker registering a confidential client with a
  metadata URL after AUTH-06 closes its sibling SSRF).
- **Console drops on production only.** Dev builds keep `console.*`
  intact so engineers can debug locally; CI / preview builds use
  `mode=production` and lose them. Same posture as `import.meta.env`
  guards.

---

## Test coverage added

`tests/Feature/LlmPb4Test.php` (14 tests):

- **LLM-01:** `UrlSafetyService` blocks loopback, AWS metadata, RFC1918;
  allows public HTTPS. Provider create-API refuses RFC1918 endpoint.
  Dispatcher refuses OpenAI provider with loopback endpoint.
- **LLM-08:** Provider create-API refuses Ollama without endpoint;
  dispatcher throws when an Ollama row has no endpoint.
- **LLM-02:** `Http::assertSent` confirms Gemini sends `x-goog-api-key`
  header and no `key=…` in URL.
- **LLM-03:** Upstream HTML error body containing a bearer token does
  NOT echo into `$result->error`.
- **LLM-04:** A transport `Throwable` containing the API key in its
  message does NOT echo into `$result->error`.
- **LLM-05:** Raw DB row for `response_text` is ciphertext (not the
  plaintext sentinel); model decrypts transparently; `response_hash`
  populated; ciphertext round-trips via `Crypt::decryptString`.
- **LLM-06:** `POST /api/v1/prompts/{u}/{s}/run` returns 429 after 30
  hits in a minute.
- **LLM-07:** Oversized prompt throws pre-dispatch.

---

## Verification

```
$ php artisan test
Tests:    491 passed (1296 assertions)
Duration: ~11s
```

Zero existing-suite regressions; PB-1/2/3 regression suites unchanged.

---

## Next

PB-5 — Web transport, deployment hardening & CI (Theme E). Closes
INFRA-01..11 + MCP-05 (Origin tightening): SecurityHeaders middleware,
SRI on Scalar, env-driven CORS allowlist, APP_DEBUG=false default,
SESSION_ENCRYPT=true / SESSION_SECURE_COOKIE=true, public/build
ungitignored, `.github/workflows/ci.yml` with tests + audits, deploy
script with backup + atomic swap + rollback.
