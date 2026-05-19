# Sprint 2 — Authorization & Multi-Tenant Isolation

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: tenant isolation across the REST API — prompt/result/pipeline/
evaluation/collection/team access, API-key prompt scoping, admin gating,
`Prompt::visibleTo`. (MCP tool authz is Sprint 5; provider secrets Sprint 4.)

Files reviewed: `app/Http/Controllers/Api/ResolvesPrompts.php`,
`app/Http/Middleware/RequireRole.php`, `app/Models/Prompt.php`,
`PromptController`, `ResultController`, `EvaluationController`,
`PipelineController`, `GraphController`, `CollectionController`,
`TeamController`, `UserController`, `LlmProviderController`,
`routes/api.php`, `bootstrap/app.php`.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### AUTHZ-01 — Standalone Result endpoints: full cross-tenant IDOR (read / update / delete) — **CRIT / Security**

`ResultController::show()`, `update()`, `destroy()`
(`ResultController.php:103-149`) use route-model binding `Result $result`
with **no ownership or `visibleTo` check whatsoever**. Routes
`GET|PATCH|DELETE /api/v1/results/{result}` (`routes/api.php:72-75`) sit under
`dual.auth`, so **any** authenticated principal — Sanctum user, any OAuth
token, or **any API key (including a prompt-scoped one)** — can iterate
integer IDs to:

- read any result's `response_text` / `rendered_content` /
  `variables_used` / `notes` (cross-tenant data exfiltration);
- overwrite `rating` / `starred` / `notes` on any result;
- **delete any Result in the system** (cross-tenant destruction).

`download()` is the *only* standalone method that checks
`Prompt::visibleTo` (`:120-126`) — proving the omission elsewhere is an
oversight, not by design.

Fix (Phase B): in `show/update/destroy` resolve via
`Prompt::visibleTo($user)` on `$result->prompt_id` (and an owner/editor gate
for mutations); add the API-key scope check (AUTHZ-05).

---

### AUTHZ-02 — Evaluation endpoints: cross-tenant IDOR + attacker-driven LLM spend — **CRIT / Security**

`EvaluationController::evaluate/index/latest/show`
(`EvaluationController.php:14-49`) bind `Result $result` with **no visibility
check**. Any authenticated principal can:

- `POST /results/{id}/evaluate` — run a **server-side LLM evaluation** (uses
  the instance's configured provider + API key) against *any* result by ID →
  cross-tenant disclosure of `response_text` into the evaluation, plus
  unbounded attacker-controlled LLM cost on someone else's data;
- read any user's evaluations (`index/latest/show`).

Fix (Phase B): enforce `Prompt::visibleTo` on `$result->prompt_id` before any
evaluation read/run; rate-limit server-side evaluation.

---

### AUTHZ-03 — Pipelines have no tenant isolation (global read/mutate/delete) — **CRIT / Security**

`PipelineController::show/update/destroy/addChannel/updateChannel/
removeChannel` (`PipelineController.php:42-163`) bind `Pipeline $pipeline`
with **no `created_by`/owner check**. `index()` (`:16-23`) returns **all
active pipelines globally** (no owner scoping). So every authenticated user
can enumerate, read (incl. channel `system_prompt` bodies), modify, **delete**,
and re-channel any user's pipeline. Pipelines are effectively an
unprotected shared namespace.

Fix (Phase B): scope `index` to owner (+ shared/admin); add an
owner-or-admin gate to every `Pipeline`-bound method.

---

### AUTHZ-04 — `runPipeline` ignores prompt visibility → cross-tenant execution + LLM spend — **HIGH / Security**

`PipelineController::runPipeline()` (`:165-209`) resolves the target prompt as
`Prompt::where('created_by',$owner->id)->where('slug',$slug)->firstOrFail()`
— **no `visibleTo()`, no `authorizePromptAccess`** — and selects the pipeline
purely by global `slug` + `is_active`. Any authenticated user can run any
pipeline against **any other user's prompt/version** (addressed by
username+slug), writing the rendered content into a Result and incurring LLM
cost on the configured provider.

Fix (Phase B): resolve the prompt through `resolvePrompt(...,$request)` (or
`visibleTo`) + `authorizePromptAccess`; require edit/run permission.

---

### AUTHZ-05 — API-key prompt scoping enforced on only 4 of ~12 prompt endpoints — **HIGH / Security**

`PromptController::authorizePromptAccess()` (`:308-316`) — the only API-key
prompt-scope gate — is invoked **solely** in PromptController
`show/update/destroy/run` (`:101,112,134,243`) plus the list filter
(`:37-39`). It is **absent** from `VersionController`, `BranchController`,
`RenderController`, `ResultController`, `EvaluationController`,
`GraphController`, `PipelineController`, `CollectionController`.

A key created with specific `prompts` attached (the documented "scoped to
specific prompts" feature, `ApiKey::prompts()` / `api_key_prompt`) can
therefore still read/write **versions**, **render**, create **branches**,
store/list **results**, run **pipelines**, and read the **graph** for every
prompt its owner can see. The scoping control is illusory outside
PromptController.

Fix (Phase B): centralize the API-key scope check (e.g. in
`resolvePrompt`/middleware) so every prompt-addressed endpoint enforces it.

---

### AUTHZ-06 — `resolvePrompt` visibility is conditional on an optional `$request` — **MED / Security (latent)**

`ResolvesPrompts::resolvePrompt()` (`:10-28`) only applies
`Prompt::visibleTo` when `$request && $request->user()`. Every current caller
passes `$request` and `dual.auth` guarantees a user, so isolation holds
**today** — but the optional-`$request` signature is a latent footgun: any
future or refactored caller (or MCP reuse) that omits it silently returns any
user's prompt by username+slug. Fail-open by default.

Fix (Phase B): make the request/user mandatory; derive the user from
`auth()` and fail closed if absent.

---

### AUTHZ-07 — `role:` middleware defined but never used; admin gating is hand-rolled per method — **MED / Security + DeadEnd**

`bootstrap/app.php:16` aliases `role => RequireRole`; it is used **zero
times** in `routes/*`. Admin enforcement is manual
`if(!$request->user()->isAdmin())` repeated in each `UserController` /
`LlmProviderController` method (correct in all current methods, verified).
Risks: dead middleware (DeadEnd); no defense-in-depth; one forgotten check on
a future admin method = privilege escalation.

Fix (Phase B): apply `role:admin` to the users/providers route groups *and*
keep controller checks; or remove the unused alias if the pattern is
deliberately controller-side (document the decision).

---

### AUTHZ-08 — Result `store` lacks API-key scope + write-permission gate — **MED / Security**

`ResultController::store()` (`:44-89`) only requires the prompt be *visible*
(read level). Any user who can see a prompt (incl. team-shared) can write
Results onto it (`created_by` = caller); no `authorizePromptAccess`
(API-key scope) and no owner/editor gate. Compounds AUTHZ-05 — a
narrowly-scoped key still injects results into any visible prompt.

Fix (Phase B): add API-key scope check + define who may write results
(owner/editor/team-member policy).

---

### AUTHZ-09 — Graph result/evaluation layers: confirm visibility derivation — **LOW / Security**

`GraphController::nodes()` correctly scopes prompts via
`Prompt::visibleTo` (`:32`), collections via `created_by` (`:40`), and
returns a `truncated` flag at `MAX_NODES=500` (`:236`) — acceptable.
Result/evaluation layers derive from `Prompt::visibleTo(...)->pluck('id')`
(`:387`) which looks correct; flagged only for an explicit Phase-B
confirmation that no result/evaluation node escapes the visible-prompt set.

---

### AUTHZ-10 — Provider `endpoint` disclosed to non-admins — **LOW / Security**

`LlmProviderController::index()` (`:20`) returns `endpoint` for active
providers to all users. `api_key` is correctly never selected. Minor internal-
infrastructure URL disclosure. (Provider secret handling → Sprint 4.)

---

## Positives (no action)

- `Prompt::scopeVisibleTo` (`Prompt.php:127-139`) correct: creator OR
  team-member; deliberate admin bypass.
- `TeamController` — `authorizeMembershipOrAdmin` / `authorizeOwnerOrAdmin`
  applied on **every** show/update/destroy/addMember/removeMember; sole-owner
  leave guard.
- `CollectionController` — `created_by` checked on **every** method
  (show/update/destroy/addItem/removeItem); `index` owner-scoped.
- `ResultController::starred` filters by `visibleTo`; `download` checks
  visibility.
- `UserController` — admin-checked everywhere; blocks self role-change and
  self-deletion.
- `PromptController` mutating methods use `authorizeOwnership` (owner/admin).

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| AUTHZ-01 | Crit | Security | ResultController.php:103-149; routes/api.php:72-75 |
| AUTHZ-02 | Crit | Security | EvaluationController.php:14-49 |
| AUTHZ-03 | Crit | Security | PipelineController.php:16-163 |
| AUTHZ-04 | High | Security | PipelineController.php:165-209 |
| AUTHZ-05 | High | Security | PromptController.php:308-316 vs Version/Branch/Render/Result/Eval/Graph/Pipeline/Collection controllers |
| AUTHZ-06 | Med | Security | ResolvesPrompts.php:10-28 |
| AUTHZ-07 | Med | Security/DeadEnd | bootstrap/app.php:16; routes/* (no `role:` use) |
| AUTHZ-08 | Med | Security | ResultController.php:44-89 |
| AUTHZ-09 | Low | Security | GraphController.php:32,40,236,387 |
| AUTHZ-10 | Low | Security | LlmProviderController.php:20 |

**Cross-cutting:** route-model-bound resources (`Result`, `Pipeline`,
`PipelineChannel`) are bound straight to controllers with no authorization
layer — three CRIT IDORs share one root cause. Recommend Phase B introduce
Laravel Policies / form-request authorization (or a binding-time visibility
scope) rather than per-method patches. This is the single highest-risk theme
in the audit so far and pairs with Sprint 1's AUTH chain.

## Next

Sprint 3 — Template Engine, Injection & DoS (report-only). Pending user
review.
