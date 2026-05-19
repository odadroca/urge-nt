# Sprint 4 — LLM Drivers & Provider Management

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: 6 LLM drivers + dispatcher, provider CRUD/test, AI assistant
service, credential handling, SSRF, plaintext storage, SPA error logging.

Files reviewed: `app/Services/LlmDispatchService.php`,
`app/Services/LlmProviders/{OpenAi,Anthropic,Mistral,Gemini,Ollama,OpenRouter}Driver.php`,
`app/Services/LlmProviders/{LlmResult.php,Contracts/}`,
`app/Models/LlmProvider.php`, `app/Services/AiAssistantService.php`,
`app/Http/Controllers/Api/LlmProviderController.php`, `config/urge.php`,
`.env.example`, SPA `console.*` usage.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### LLM-01 — Admin-set provider `endpoint` becomes server-side SSRF with the bearer credential attached — **HIGH / Security**

`LlmDispatchService::resolveDriver()` `:38` passes `$provider->endpoint`
into `OpenAiDriver` as `baseUrl`. `OpenAiDriver::send()` then does
`Http::withToken($apiKey)->post("{$base}/v1/chat/completions", …)`
(`OpenAiDriver.php:33-43`) with **no scheme/host allowlist, no block of
loopback / RFC1918 / 169.254.169.254 / link-local / IPv6 ULA**. A provider
configured with `endpoint = http://169.254.169.254/...` (or any internal
URL) drives a fully attacker-influenced authenticated POST from the server,
**including the `Authorization: Bearer <api_key>` header**. Any endpoint
that echoes request headers (logging proxy, requestbin, internal debug
service) → instant credential exfil into the URGE DB via LLM-03 below.

Same SSRF surface in `OllamaDriver` `baseUrl` (`:42`) without a bearer
header (pure SSRF, no token leak there); same in `OpenAiDriver` for
"OpenAI-compatible" deployments (advertised use). Admin-only today
mitigates blast radius; AUTHZ-07 (hand-rolled admin gating) makes one
regression sufficient to escalate to any authenticated user.

Fix (Phase B): scheme=https-only, public-IP allowlist with DNS-rebind
protection, redirects disabled; drop credentials when destination is not on
allowlist; treat `endpoint` as security-sensitive input.

---

### LLM-02 — Gemini puts the API key in the URL → exception messages leak it into Results and the UI — **HIGH / Security**

`GeminiDriver::send()` `:32`:
`…/v1beta/models/{model}:generateContent?key={$this->apiKey}`.

Each driver catches `Throwable` and returns `LlmResult::failure(
$e->getMessage(), …)` (Gemini `:65-68`, same in every driver). Many
connection / TLS / DNS / cURL exceptions include the full request URL in
their message. The failure path then surfaces as:

- the JSON returned to the SPA (e.g. `LlmProviderController::test` `:113`
  echoes `"Error: {$e->getMessage()}"` to admin UI),
- the `results.error_message` column written for every failing run
  (`PromptController` run pipeline),
- the SPA `console.error('… failed:', err)` dump (LLM-11).

So **any transport failure of a Gemini call can persist the API key into
the database and the SPA**.

Fix (Phase B): send Gemini key via header (`x-goog-api-key`) instead of
URL; redact key from any echoed error; sanitize URLs in driver error paths.

---

### LLM-03 — Upstream response body echoed verbatim as `error` into stored Results and clients — **HIGH / Security**

Every driver: `$error = $response->json('…') ?? $response->body();`
(`OpenAiDriver:48`, `AnthropicDriver:54`, `MistralDriver:46`,
`GeminiDriver:51`, `OpenRouterDriver:69`). When upstream returns an HTML
error page, a proxy/WAF page, or a structured error containing echoed
headers/payload, the entire body is returned as the driver error,
written to `results.error_message`, and rendered in the SPA. Combined with
LLM-01: pointing `endpoint` at any service that echoes the Authorization
header (debug endpoint, request inspector, internal logger) yields
immediate token exfil into the URGE DB on the first failing call. Same
attack surface without LLM-01 if any operator-controlled provider points
at a misconfigured upstream.

Fix (Phase B): cap and sanitize echoed error bodies; strip headers/keys
from upstream payloads; only echo a known set of provider-specific error
fields with size limit.

---

### LLM-04 — `Throwable` reflection without sanitization in admin test/run endpoints — **MED / Security**

`LlmProviderController::test()` `:111-115` returns raw `$e->getMessage()`
to admin UI; `PromptController::run()` similarly persists exception text
into `error_message`. Tied to LLM-02/03; also enables an admin tricked
into "test"-ing a provider pointed at an attacker URL to repeatedly
fingerprint internal services with the configured bearer.

Fix (Phase B): allowlist-only error classes; generic user-facing message +
opaque server-side correlation id for the real exception.

---

### LLM-05 — Plaintext at-rest storage of prompt content, rendered_content, response_text, error_message, variables_used — **MED / Security**

`PromptVersion.content` (`longText`); `Result.{rendered_content,
response_text, error_message, notes, variables_used}` (JSON for the last)
are all unencrypted columns. No `encrypted` Eloquent cast on any of them.
Combined with:

- AUTHZ-01/02 (cross-tenant read of Result/Evaluation),
- HYGIENE-01 (logs and DB-adjacent artefacts were VCS-tracked),
- LLM-03 (upstream body persisted into `error_message`),

a single DB dump / backup leak exposes the entire prompt/result corpus —
likely user PII, business secrets, and any credentials a user pasted into
a prompt or that arrived via LLM-03.

Fix (Phase B): app-layer encryption (`encrypted` cast) on these columns,
or operator-enforced DB encryption-at-rest with documented backup hygiene;
add a "secret-shaped-string" detector at write time and refuse/redact.

---

### LLM-06 — Session/OAuth callers are unthrottled on LLM-dispatching endpoints; only API keys are rate-limited — **MED / Security**

`ApiKeyAuthentication` enforces 60/min/key
(`ApiKeyAuthentication.php:30-41`), but Sanctum and OAuth callers go
through `DualAuthentication` with **no throttle**. The LLM-firing endpoints
`POST /providers/{id}/test`, `POST /prompts/{slug}/run`, and the
cross-tenant `POST /prompts/{slug}/run-pipeline` (AUTHZ-04) can be called
at any rate by any authenticated session/OAuth principal → unbounded
attacker-controlled spend on the instance's configured LLM provider keys.

Fix (Phase B): apply per-user throttle to LLM-dispatching routes (and to
the SSE/MCP tool dispatch in Sprint 5); separate quota for `evaluate`.

---

### LLM-07 — Long driver timeouts + no input-token / prompt-size cap → DoS amplification of TPL-04 — **MED / Security**

Timeouts: OpenAi/Anthropic/Mistral/Gemini/OpenRouter `120s`; Ollama
`300s`. No cap on rendered prompt size before dispatch. Combined with
TPL-04 (billion-laughs include expansion) a single render can produce a
multi-megabyte prompt that blocks a PHP worker for up to 5 minutes upstream
(plus pre-dispatch render cost).

Fix (Phase B): cap rendered-prompt size pre-dispatch; lower default
timeouts; add a queue/job for long-running dispatch.

---

### LLM-08 — Ollama default endpoint silently hits localhost:11434 — **LOW / Security**

`LlmDispatchService::resolveDriver` `:42`:
`$provider->endpoint ?? 'http://localhost:11434'`. On a shared/hosted
deploy with the `ollama` driver and no endpoint set, requests hit whatever
serves :11434 on the host. SSRF-adjacent surprise behavior; LOW given
admin-only.

Fix (Phase B): require an explicit endpoint for `ollama`; reject null.

---

### LLM-09 — `.env.example` does not document `CURL_SSL_VERIFY` — **LOW / Drift**

Default in code is `true` (verified — every driver respects it,
`OpenRouterDriver:43-44` sets both `CURLOPT_SSL_VERIFYPEER` and
`CURLOPT_SSL_VERIFYHOST=2`). But the knob is undocumented in
`.env.example`, so operators can neither knowingly keep it on nor turn it
off correctly (Laravel `env()` only casts `'true'/'false'` so
`CURL_SSL_VERIFY=0` stays truthy — safe-by-default but confusing).

Fix (Phase B): add `CURL_SSL_VERIFY=true` to `.env.example` with a
comment; refuse `false` outside `local` env.

---

### LLM-10 — `LlmProvider.settings` JSON column is plaintext + array cast → silent secret sprawl risk — **LOW / Security**

`LlmProvider.php:19-21`: `api_key` is encrypted, `settings` is `array`
(plaintext JSON). The field is intended for non-secret config but nothing
prevents an operator from pasting secondary keys/tokens (custom-headers,
proxy credentials) there.

Fix (Phase B): schema-validate `settings` to a known whitelist; cast as
`encrypted:array` if free-form is required.

---

### LLM-11 — SPA `console.error('… failed:', err)` dumps full Axios errors in production — **LOW / Security**

27 occurrences enumerated (e.g. `ResultsPanel.jsx:30,39,49`,
`Editor.jsx:54`, `LlmProvidersTab.jsx:101,111`, `useCopyToClipboard.js:13`).
`err` is the full Axios error object — request URL, payload, response body
— printed to the user's own browser devtools. Not transmitted (so not a
classic "leak"), but realistic risk: screenshots in bug reports,
shared-device shoulder-surfing, malicious browser extensions reading
console. Compounds LLM-02 (Gemini key) and LLM-03 (echoed upstream body)
which would land in `err.response.data`.

Fix (Phase B): central error helper that logs only `err.message` +
correlation id in production; strip with Vite `drop_console` for prod
build.

---

## Positives (no action)

- **No driver disables SSL verification**: every driver uses
  `config('urge.curl_ssl_verify', true)` (default true); OpenRouter raw
  cURL sets both `VERIFYPEER` and `VERIFYHOST=2`.
- `LlmProvider.api_key` cast `encrypted` + `$hidden = ['api_key']`
  (`LlmProvider.php:19-25`) → never JSON-serialized to API responses.
- `LlmDispatchService::resolveDriver` rejects missing key (non-Ollama)
  early (`:33-35`).
- All driver constructors `readonly`; no mutable provider state across
  requests; no static caches of credentials.
- No `eval()` / `exec()` of returned text anywhere; LLM output treated as
  data only.
- `AiAssistantService` does not persist its responses; transient only.
- `LlmProviderController.index` correctly omits `api_key` and `settings`
  from non-admin payload (`:20`) — refines AUTHZ-10.

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| LLM-01 | High | Security | LlmDispatchService.php:38,42; OpenAiDriver.php:33-43 |
| LLM-02 | High | Security | GeminiDriver.php:32,65-68; LlmProviderController.php:111-115 |
| LLM-03 | High | Security | OpenAi:48, Anthropic:54, Mistral:46, Gemini:51, OpenRouter:69 |
| LLM-04 | Med | Security | LlmProviderController.php:97-117 |
| LLM-05 | Med | Security | PromptVersion / Result migrations + models (no `encrypted` cast) |
| LLM-06 | Med | Security | ApiKeyAuthentication.php:30-41; DualAuthentication.php |
| LLM-07 | Med | Security | drivers timeouts 120-300s; TemplateEngine (no size cap) |
| LLM-08 | Low | Security | LlmDispatchService.php:42 |
| LLM-09 | Low | Drift | .env.example (no CURL_SSL_VERIFY) |
| LLM-10 | Low | Security | LlmProvider.php:19-21 |
| LLM-11 | Low | Security | 27 `console.error` in resources/js/spa/** |

**Cross-cutting:** LLM-01 + LLM-02 + LLM-03 chain into "provider config →
credential exfil into the DB and SPA". Together with TPL-04 (DoS
amplification) and AUTHZ-04 (cross-tenant pipeline run) the LLM layer is
the second-highest-impact theme after the AUTHZ IDORs. Recommend
addressing LLM-01/02/03 together in Phase B (they share the same
"error-text and URL hygiene" boundary).

## Next

Sprint 5 — MCP Server (HTTP + stdio): tool dispatch, session handling,
scope mapping for all 31 tools, JSON-RPC injection, rate-limit bypass.
Report-only.
