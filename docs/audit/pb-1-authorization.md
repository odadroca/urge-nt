# PB-1 — Authorization Layer (remediation)

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes the 4 audit CRITs and 9 HIGH/MED findings.
**Suite:** 417 → **450 passing** (33 new regression tests).

This is the first remediation sprint of Phase B. It implements the
Theme A direction from `docs/audit/08-summary.md`: a Laravel Policy
layer shared between REST and MCP via a single `AuthorizationService`,
centralized API-key prompt-scope enforcement at prompt resolution time,
and `role:admin` middleware as defense-in-depth alongside controller
checks.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **AUTHZ-01** | Crit | `ResultController::{show,update,destroy,download}` now call `authorizeResultAccess()` → 404 on cross-tenant read, 403 on cross-tenant mutate |
| **AUTHZ-02** | Crit | `EvaluationController::*` route every method through `authorizeResultAccess()` (same gate as Result) |
| **AUTHZ-03** | Crit | `PipelineController::{show,update,destroy,addChannel,updateChannel,removeChannel}` call `authorizePipeline()`; `index` scopes by owner unless admin |
| **MCP-01** | Crit | `McpToolHandler::{updatePipelineTool,deletePipelineTool,addChannelTool,updateChannelTool,removeChannelTool}` enforce `AuthorizationService::userOwnsPipeline()` (channels resolve through `channel->pipeline`) |
| **AUTHZ-04** | High | `PipelineController::runPipeline` now resolves the prompt via `resolvePrompt` (visibility + scope) and calls `authorizePipeline($pipeline,'run')` |
| **AUTHZ-05** | High | API-key prompt-scope check promoted from `PromptController::authorizePromptAccess` into `ResolvesPrompts::resolvePrompt` (via `AuthorizationService::enforceApiKeyScope`). Now applied **automatically** on every endpoint using the trait: prompts, versions, branches, render, results-list, share. Standalone Result/Evaluation controllers re-check via the result's parent prompt |
| **AUTHZ-06** | Med | `ResolvesPrompts::resolvePrompt` now requires `Request` (was optional) and 401s if no authenticated user — fails closed |
| **AUTHZ-07** | Med | `role:admin` middleware applied to `users.*` and provider-mutation routes in `routes/api.php`, alongside the existing per-method `isAdmin()` checks |
| **AUTHZ-08** | Med | `ResultController::store` now calls `$user->can('writeResult', $prompt)` via `PromptPolicy` — write-permission is now an explicit policy ability (today equivalent to visibility) |
| **MCP-02** | High | `runPipeline` MCP tool enforces `userOwnsPipeline` |
| **MCP-03** | Med | `updateResult` / `deleteResult` MCP tools enforce `userCanMutateResult` (not just visibility) |
| **MCP-04** | Med | `pinVersion` / `archiveVersion` MCP tools call `verifyOwnership` before mutating |
| **DEAD-03** | Med | Legacy `GET /api/v1/prompts/{slug}` redirect route + `PromptController::legacyRedirect` method deleted — was a global slug lookup that leaked owner_slug cross-tenant |
| **DEAD-05** | Low | `GET /api/v1/mcp` 405-only route + `McpController::stream` method removed; Laravel's default 405 covers it |
| **AUTH-12** | Low | `ApiKeyService::generateKey` now sets `is_active => true` explicitly (was relying on DB column default) |

**Deferred to later PBs** (per scope):
- AUTHZ-09 (graph result/eval layer): regression test added confirming it's correct today.
- AUTHZ-10 (provider endpoint disclosed to non-admin): provider hygiene → PB-4.
- MCP-08 (API keys bypass mcp:* scopes): documented design; the cross-
  tenant risk it amplified (MCP-01) is now closed. Decision deferred
  to PB-6 (docs sprint) — either document the design clearly or treat
  as a breaking change.

---

## Architecture

### New files

- `app/Services/AuthorizationService.php` — single source of truth for
  prompt visibility/ownership, result visibility/mutation, pipeline
  ownership, and API-key prompt scoping. **Used directly by MCP tool
  handlers (no Request context); wrapped by Policies for REST.**
- `app/Policies/PromptPolicy.php` — `view`, `update`, `delete`, `share`,
  `pin`, `archive`, `writeResult`, `run` abilities.
- `app/Policies/ResultPolicy.php` — `view`, `update`, `delete`,
  `evaluate` abilities.
- `app/Policies/PipelinePolicy.php` — `view`, `create`, `update`,
  `delete`, `run`, `manageChannels` abilities.

Laravel 11+ auto-discovers `App\Policies\{Model}Policy` for
`App\Models\{Model}`. No registration needed.

### Modified files

- `app/Http/Controllers/Api/ResolvesPrompts.php` — `$request` now
  required (was optional); centralized `enforceApiKeyScope` call;
  always fails closed.
- `app/Http/Controllers/Api/{Result,Evaluation,Pipeline,Prompt}Controller.php`
  — wired to policies and the new resolvePrompt signature; redundant
  `PromptController::authorizePromptAccess` removed (now in the trait).
- `app/Services/McpToolHandler.php` — owner checks on pipeline-mutation
  tools (incl. channels via `channel->pipeline`), run_pipeline,
  pin_version, archive_version; result-write tools require mutation
  permission; missing `PipelineChannel` import added (latent bug —
  the channel tools would have errored on first invocation).
- `app/Http/Controllers/McpController.php` — `stream()` removed
  (DEAD-05); accompanying `GET /api/v1/mcp` route removed.
- `routes/api.php` — `role:admin` middleware groups for users and
  provider mutations; legacy `/prompts/{slug}` route removed.
- `app/Services/ApiKeyService.php` — explicit `is_active => true`.

### Decision points

- **API-key scope for Result writes (AUTHZ-08):** `writeResult` policy
  today returns `userCanSeePrompt` — preserves the existing UX where
  team members can store results on shared prompts. The policy gives us
  a future tightening point (e.g. owner+editor-only) without scattering
  if/else.
- **Pipeline visibility is owner-only:** matches collections; no
  team-shared pipelines today. If team-shared pipelines become a
  product feature, only `PipelinePolicy::view`/`run` need to change.
- **`role:admin` belt-and-braces:** middleware applied **and** per-
  method `isAdmin()` checks kept. One forgotten check on a future admin
  method now lands a 403 from middleware before reaching the handler.

---

## Test coverage added

`tests/Feature/Api/AuthorizationPb1Test.php` (23 tests):
- Cross-tenant `GET|PATCH|DELETE /results/{id}` → 404 (AUTHZ-01)
- Cross-tenant `GET /results/{id}/download` → 404 (AUTHZ-01)
- Cross-tenant `POST /results/{id}/evaluate` + `GET ... /evaluations*` → 404 (AUTHZ-02)
- Pipeline `index` does not leak other tenants; `show` → 404; mutate routes → 403 (AUTHZ-03)
- Cross-tenant `runPipeline` → 404 on visibility (AUTHZ-04)
- API-key scope enforced on versions, render, results-list, branches, result-show (AUTHZ-05)
- `users` and provider-mutation routes → 403 for non-admin (AUTHZ-07)
- Generated API key has `is_active=true` (AUTH-12)
- Graph nodes do not leak cross-tenant (AUTHZ-09)

`tests/Feature/McpAuthorizationPb1Test.php` (10 tests):
- All pipeline-mutation tools refuse non-owner (MCP-01)
- `runPipeline` tool refuses cross-tenant (MCP-02)
- `deleteResult`/`updateResult` tools require ownership not just
  visibility (MCP-03)
- `pinVersion`/`archiveVersion` refuse non-owner (MCP-04)

Pattern: each test creates a sacrificial admin first (Laravel's first-
user-auto-admin rule would otherwise mask cross-tenant failures), then
two non-admin tenants (`alice`, `bob`).

---

## Verification

```
$ php artisan test
Tests:    450 passed (1218 assertions)
Duration: ~9s
```

Baseline 417 → 450 (delta +33 = exactly the new regression tests).
Zero regressions in the existing suite.

---

## Next

PB-2 — OAuth & GitHub identity (Theme F). Closes AUTH-01..11.
