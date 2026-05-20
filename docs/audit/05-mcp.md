# Sprint 5 — MCP Server (HTTP + stdio)

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: Streamable-HTTP MCP transport, stdio MCP transport, JSON-RPC
processing, session handling, Origin check, rate limiting, scope mapping
across the 31 tools, per-tool authorization, resource read endpoints.

Files reviewed: `app/Http/Controllers/McpController.php`,
`app/Services/McpToolHandler.php` (1934 lines),
`app/Console/Commands/McpServerCommand.php`,
`app/Models/OAuthAccessToken.php` (`hasScope`), `routes/api.php:166-168`.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### MCP-01 — Pipeline-mutation tools have **no owner check** (cross-tenant via MCP) — **CRIT / Security**

Mirrors AUTHZ-03 inside the MCP surface. None of these tools verify the
caller owns the pipeline:

| Tool | Location | Lookup |
|---|---|---|
| `update_pipeline` | `:1725-1752` | `Pipeline::where('slug', …)` global |
| `delete_pipeline` | `:1754-1768` | global by slug |
| `add_channel` | `:1770-1822` | global by `pipeline_slug` |
| `update_channel` | `:1825-1873` | `PipelineChannel::find($id)` global |
| `remove_channel` | `:1875-1890` | `PipelineChannel::find($id)` global |

Any authenticated MCP principal — OAuth token with the appropriate scope
**or** any API key (which bypasses scope, MCP-08) **or** Sanctum session —
can mutate, delete, or re-channel any user's pipeline. OAuth scope
(`mcp:write`/`mcp:admin`) gates the *capability*, not the *tenant*; the
required per-tenant check is missing. Note that `delete_prompt`,
`share_prompt`, and `update_result`/`delete_result` (partially) **do**
call `verifyOwnership` / visibility — the omission on pipelines/channels
is plainly oversight.

Fix (Phase B): owner-or-admin gate on every pipeline/channel mutation
tool (shared with REST policy from AUTHZ-03).

---

### MCP-02 — `run_pipeline` tool resolves pipelines globally → cross-tenant execution + LLM spend — **HIGH / Security**

`runPipeline()` (`:1645-1697`) resolves the **prompt** with
`resolvePrompt(..., $user)` (visibility-checked) but selects the
**pipeline** by global slug + `is_active` (`:1656-1658`) with no
ownership/visibility scope, then dispatches via `PipelineService::run`.
Mirrors AUTHZ-04 inside MCP — and inherits TPL-02 (PipelineService renders
with null user → cross-tenant fragment disclosure into the LLM call).

Fix (Phase B): scope pipeline lookup to caller's owned / shared
pipelines.

---

### MCP-03 — `delete_result` / `update_result` enforce visibility, not ownership — **MED / Security**

`deleteResult()` (`:1440-1462`) and `updateResult()` (`:1394-1438`) only
check `Prompt::visibleTo($user)` on the parent prompt. Any **team
member** of a shared prompt can therefore delete or modify another user's
Results on that prompt (cross-user destruction / silent rating/star/note
overwrite). Same authorization sloppiness pattern as Sprint 2 AUTHZ-01,
but the MCP path at least scopes by visibility — REST didn't even do
that.

Fix (Phase B): require result-owner (`created_by`) or prompt-owner or
admin for write/delete on Results.

---

### MCP-04 — `pin_version` / `archive_version` mutate without `verifyOwnership` — **MED / Security**

`pinVersion()` (`:1892-1912`) and `archiveVersion()` (`:1914-`) resolve
the prompt with `resolvePrompt(... user)` (visibility-checked) but then
mutate `pinned_version_id` / `archived_at` **without an ownership
check** (`verifyOwnership` is not called — compare `deletePrompt` `:1342`
where it is). Any team member with read access to a shared prompt can
silently change which version is "active" (pin) or archive a version on
someone else's prompt — operationally disruptive, hard to detect.

Fix (Phase B): call `verifyOwnership` before mutating.

---

### MCP-05 — Origin allowlist effectively allows any HTTPS site — **MED / Security**

`McpController::isAllowedOrigin()` (`:251-277`): returns `true` for the
app URL, localhost, an explicit Claude.ai/extensions list, **and then
also `true` for ANY origin starting with `https://`** (`:273-275`). The
inline comment justifies it ("Auth is the real gate") but that defeats
the point of the allowlist. Practical exploit is currently blunted by
two facts:

- the MCP response sets no `Access-Control-Allow-Origin` header, so
  browser CORS blocks cross-origin fetch from the response side;
- SameSite=lax + JSON POST limits drive-by Sanctum-cookie attacks.

Still: this is a tripwire that doesn't trip. A future CORS-permissive
change (or a cooperating malicious MCP client embedded in a third-party
site) makes Origin a real defense layer; today it isn't one.

Fix (Phase B): tight allowlist (config-driven), drop the
"any HTTPS" branch; document the threat model the allowlist defends.

---

### MCP-06 — Handler `resolvePrompt` global fallback when `$user` is null — **MED / Security**

`McpToolHandler::resolvePrompt()` `:732-771`: line 770
`// No user context: global slug lookup` returns **any tenant's prompt by
slug** if no user is supplied. The HTTP MCP controller refuses
unauthenticated calls (`McpController:37-45`) so the HTTP path doesn't
reach this branch — but: (a) this is the same TPL-02 fail-open theme
that bit the share page and pipeline runs, and (b) the stdio command
falls through to `User::first()` (MCP-10) which may itself be null on an
empty DB → global fallback fires.

Fix (Phase B): remove the no-user branch; fail closed.

---

### MCP-07 — `urge://prompts` resource fail-opens to global list when user null — **MED / Security**

`readResource()` (`:572-595`): `if ($user) { $query->visibleTo($user); }`
— **without** a user, returns ALL prompts in the system. HTTP MCP
controller blocks null-user, so reachable only via the stdio path
(MCP-10).

Fix (Phase B): require user; refuse the resource if absent.

---

### MCP-08 — API keys bypass `mcp:read|write|admin` scope by design → least-privilege keys can call admin tools — **MED / Security**

`McpController::handleToolCall()` `:208-219` enforces scope **only when
`$oauthToken` is present**. API-key callers therefore bypass scope
entirely. Documented ("scope enforced on OAuth tokens only"), but the
implication is not surfaced: a prompt-scoped API key — sold to users as
"this key is restricted to these prompts" — can call **`delete_pipeline`,
`remove_channel`, `delete_result`** etc. Combined with MCP-01 (no owner
check on pipeline mutations), an API key with zero prompts in its scope
can still delete arbitrary pipelines and channels.

Same applies to Sanctum sessions (no scope check), but the SPA does not
use MCP. The risk is concentrated on API keys.

Fix (Phase B): enforce scope on **every** authenticated path, regardless
of credential type; or document that API keys imply `mcp:admin`.

---

### MCP-09 — Protocol-version drift between transports — **LOW / Drift**

`McpController:167` advertises `2025-06-18`; `McpServerCommand:47`
advertises `2024-11-05`. Capabilities differ
(`tools.listChanged: true` HTTP vs `false` stdio). Single-source-of-truth
violation; benign today but a foot-gun for clients.

Fix (Phase B): centralize the capabilities object in `McpToolHandler`;
adopt a single protocol version.

---

### MCP-10 — Stdio command silently impersonates `User::first()` — **MED / Security**

`McpServerCommand.php:17`: `$user = $userId ? User::find($userId) :
User::first()`. Without `--user`, the stdio server runs *as the first
user in the database* — usually the auto-admin from initial setup. No
warning, no opt-in. Anyone with shell access executing
`php artisan urge:mcp-server` becomes the admin. If the DB is empty,
`User::first()` is null and unauthenticated fallbacks (MCP-06/07) kick
in.

Fix (Phase B): require `--user` explicitly; fail closed if omitted; log
which user is impersonated.

---

### MCP-11 — Session cache binds Mcp-Session-Id ↔ user-id without bearer rebinding check — **LOW / Security**

`McpController.php:78-87`: `Mcp-Session-Id` validates only "session
exists" against `Cache::has(…)`; it does not check that the current
bearer's user matches `Cache::get("mcp_session:{$sessionId}")`. The
session stores `$request->user()->id` on init (`:96`) and refreshes it
on every request (`:86`) — so a leaked session ID is effectively a soft
state cookie, the real authority is the bearer.

Impact is muted (auth is still required), but the comment-stated "session
isolation" is illusory. Two clients sharing the same Mcp-Session-Id with
different bearers won't be distinguished by the session layer.

Fix (Phase B): cache `(sessionId, user_id, token-hash)`; reject on
mismatch.

---

### MCP-12 — `handleToolCall` returns full tool output as a single JSON string → echoes large/sensitive content — **LOW / Security**

`McpController.php:234`:
`json_encode($result, JSON_PRETTY_PRINT)` packs entire tool results into
one `content.text` block (e.g. `getResults` returns full
`response_text`/`notes`/`variables_used`, MCP-12 below). Combines with
LLM-03 (driver echoes upstream body into Result `error_message`) → MCP
becomes an exfil channel for whatever the upstream returned.

Fix (Phase B): cap response size; redact secret-shaped fields; structured
content blocks instead of one JSON string.

---

### MCP-13 — `handleResourceRead` swallows errors as empty `contents` — **LOW / Bug**

`McpController.php:244-247`: `if (isset($resource['error'])) { return
['contents' => []]; }` returns an empty array on every failure mode (not
found, not authorized, internal error). Client can't distinguish — masks
authorization failures.

Fix (Phase B): return proper JSON-RPC error codes per case.

---

### MCP-14 — MCP rate limit is per user only — **LOW / Security**

`McpController:48-56`: `RateLimiter` key is `mcp:{user_id}`, 60/60s. A
compromised OAuth/API-key credential can saturate the legitimate user's
budget. Standard caveat; lower priority.

Fix (Phase B): add an IP-component and a global ceiling; lower default
during abuse.

---

## Positives (no action)

- **Scope mapping is complete** for all 31 tools (refutes the Sprint-0
  worry): every tool name in `callTool` `:537-567` is covered by
  `getRequiredScope` `:30-62`, including the recently added
  `pin_version` / `archive_version`. The drift is purely in
  documentation (DOC-DRIFT-02), not in code.
- HTTP MCP rejects unauthenticated calls and returns the correct
  `WWW-Authenticate` discovery header (`:42-44`).
- Session ID is `Str::uuid()` — sufficiently random.
- JSON-RPC: notifications correctly return 204 (`:73`);
  error codes are correct (`-32600 Invalid Request`, `-32601 Method not
  found`, etc.).
- `delete_prompt` (`:1342`) and `share_prompt` (`:1475`) properly call
  `verifyOwnership`.
- `renderPrompt` calls TemplateEngine with `strict: true` and the
  authenticated user (`:1238`).
- API-key dispatcher still tracks `last_used_at` (`McpController:153`).

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| MCP-01 | Crit | Security | McpToolHandler.php:1725-1890 |
| MCP-02 | High | Security | McpToolHandler.php:1645-1697 |
| MCP-03 | Med | Security | McpToolHandler.php:1394-1462 |
| MCP-04 | Med | Security | McpToolHandler.php:1892-1929 |
| MCP-05 | Med | Security | McpController.php:251-277 |
| MCP-06 | Med | Security | McpToolHandler.php:732-771 |
| MCP-07 | Med | Security | McpToolHandler.php:572-595 |
| MCP-08 | Med | Security | McpController.php:208-219 |
| MCP-09 | Low | Drift | McpController.php:167 vs McpServerCommand.php:47 |
| MCP-10 | Med | Security | McpServerCommand.php:17 |
| MCP-11 | Low | Security | McpController.php:78-96 |
| MCP-12 | Low | Security | McpController.php:234 |
| MCP-13 | Low | Bug | McpController.php:244-247 |
| MCP-14 | Low | Security | McpController.php:48-56 |

**Cross-cutting:** MCP-01 + MCP-08 = a least-privilege API key can delete
any pipeline. MCP-02 + TPL-02 + AUTHZ-04 = the same cross-tenant
pipeline-execution + private-fragment-disclosure chain is reachable from
both REST and MCP. The MCP path duplicates REST authz patterns but with
worse coverage on pipelines/channels and partial coverage on Results —
Phase B should consolidate this in a single Policy layer used by both
surfaces.

## Next

Sprint 6 — Web transport, configuration & deployment/CI hardening.
Report-only. Pending user review.
