# Sprint 7 — Documentation, Dead-Ends & Drift

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: reconcile every documentation claim against measured Sprint 0
facts; hardcoded production domains; missing governance docs; dead code
& dead routes; OpenAPI parity with the actual API surface; status of
in-repo plan documents; legacy-folder treatment; recon corrections.

Files reviewed: `README.md`, `CLAUDE.md`, `documentation/*.md`,
`docs/*.md`, `docs/legacy/`, `public/openapi.json`,
`app/Services/AiAssistantService.php`, `bootstrap/app.php`,
`routes/{api,web}.php`, `app/Http/Controllers/Api/PromptController.php`,
`resources/js/spa/pages/PipelinesPage.jsx`.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### DOC-01 — `LICENSE` file does not exist; README advertises MIT — **HIGH / Operational + Drift**

`README.md:125`: `[MIT](LICENSE)`. There is **no `LICENSE` file** at the
repo root. The README link is dead; the project ships no license text.
Consequences:

- Forkers / contributors have no legal grant to use, modify, or
  redistribute the code (default copyright applies → "all rights
  reserved").
- The MIT claim in README is unenforceable / misleading; a downstream
  user citing MIT has no evidence trail.

Fix (Phase B): commit a real `LICENSE` (MIT to match the README intent),
or update README to reflect actual licensing.

---

### DOC-02 — Test-count claims wrong across 3 surfaces — **MED / Drift**

| Source | Claimed | Actual |
|---|---|---|
| `README.md:104` | 386 passing | **417** |
| `CLAUDE.md:19` (comment) | 410 tests | **417** |
| `CLAUDE.md:351` (status) | 410 tests passing | **417** |

Reaffirms DOC-DRIFT-01. The fact that two docs disagree (386 vs 410)
shows there's no mechanism keeping them honest. Combined with INFRA-07
(no CI), the count will drift again.

Fix (Phase B): generate the count from CI; or remove the count from
prose entirely and link to CI output.

---

### DOC-03 — MCP tool count wrong in 5 surfaces — **MED / Drift**

| Source | Claimed | Actual |
|---|---|---|
| `README.md:19` | 29 tools | **31** |
| `README.md:94` | 29 tools | **31** |
| `CLAUDE.md` (tool catalog) | 29 listed | **31 registered** |
| `documentation/architecture.md:372` | 29 tools | **31** |
| `documentation/mcp-clients.md` | 29 tools | **31** |

Undocumented tools: `pin_version`, `archive_version`. Both are correctly
scope-mapped in code (Sprint 5 positive); the gap is purely
documentation. Reaffirms DOC-DRIFT-02.

Fix (Phase B): publish a canonical tool list (generated from
`McpToolHandler::getToolDefinitions()`); cite from docs.

---

### DOC-04 — `claude-skill.md` base URL hardcoded to maintainer's dev domain — **MED / Drift**

`documentation/claude-skill.md:8,42,50,54,61` all hardcode
`https://urge-next.acordado.org/api/v1`. Users following the skill will
hit the upstream maintainer's dev instance instead of their own. The
docs file is templated to be loaded into Claude Projects, where this
mistake becomes invisible.

Fix (Phase B): replace with `{your-urge-instance}/api/v1` placeholder
and instruct readers to substitute.

---

### DOC-05 — OpenAPI parity: 20 paths documented vs **93 actual API routes** — **MED / Drift**

`public/openapi.json` lists 20 paths (`grep -cE '^\s+"/'` = 20);
`php artisan route:list` shows 93 routes under `api/v1`. ~78% of the
API surface is undocumented in the public OpenAPI spec. Compounds
INFRA-05 (public OpenAPI is a recon aid): an attacker sees an
under-representation of the surface, but a curious operator also gets
mismatched documentation when integrating. Endpoints visibly missing:
all of `pipelines/*/channels`, `providers/*`, `api-keys`, `users`,
`graph/*`, `share-links`, `evaluation-settings`, OAuth discovery, the
download endpoints, `prompts/{username}/{slug}/run` and `…/share`.

Fix (Phase B): regenerate the spec from routes (e.g. via
`l5-swagger` or hand-curated to parity); or restrict the spec to what's
intentionally exposed and gate `/openapi.json`.

---

### DOC-06 — OpenAPI `info.version: "4.0.0"` while project is "URGE v2" — **LOW / Drift**

`public/openapi.json:6`: `"version": "4.0.0"`. Project brand is URGE v2;
README, CLAUDE.md, deploy domain all use v2. The OpenAPI version is
either a stale legacy bump or a separate API-version number — unclear
from the spec.

Fix (Phase B): align version to the project's release number; or split
to `info.version` (spec version) + `info.x-product-version` (URGE).

---

### DOC-07 — In-repo plan docs not marked DELIVERED — **MED / Drift**

| File | Status in repo | Reality |
|---|---|---|
| `docs/2026-04-23-oauth-refresh-tokens-plan.md` | Plan tone, no status | **Delivered** (migration `create_oauth_refresh_tokens_table.php` exists; `OAuthService::refreshToken` implemented) |
| `docs/2026-04-23-workspace-feature-sprints.md` | Plan tone, no status | **Delivered** (`DiffViewer.jsx`, `AutocompleteDropdown.jsx`, `VisualComposer.jsx` all in `resources/js/spa/components/workspace/`) |

A casual reader sees "plan" and assumes pending work. The doc agent in
Sprint 0 read it that way ("status unclear" / MEDIUM-risk flag).

Fix (Phase B): add a `Status: Delivered (commit/PR ref)` header at the
top, or move to `docs/legacy/`.

---

### DOC-08 — Missing governance files (`SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md`) — **MED / Process**

Confirmed absent at repo root. `SECURITY.md` is the most material gap:
this audit has surfaced 4 CRIT + 9 HIGH issues across REST/MCP/template/
LLM, and there is **no documented vulnerability disclosure path** for
external researchers. A vulnerability reporter today must open a public
GitHub issue (which is itself the leak channel).

Fix (Phase B): commit `SECURITY.md` with private disclosure contact +
expected response SLA; basic `CONTRIBUTING.md`; `CHANGELOG.md` (or use
GitHub Releases).

---

### DOC-09 — README "Built With" still leads with Alpine.js — **LOW / Drift**

`README.md:121` lists Alpine.js prominently among "Built With", but
Alpine is now confined to the toast component in two Blade auth
layouts (Sprint 2 / Sprint 5 docs ack this). Misrepresents the stack.

Fix (Phase B): drop Alpine from the Built With section (or relegate
with a note).

---

### DEAD-01 — `AiAssistantService` has **zero callers** — **MED / DeadEnd**

`grep -rn 'AiAssistantService\|summarizeDifferences\|suggestImprovements'`
across `app/`, `resources/`, `routes/` returns **no callers**
(self-references excluded). The service exposes
`summarizeDifferences()` (used by the documented "diff summary" UI) and
`suggestImprovements()` (documented "improvement suggestions"), but
neither is invoked anywhere — a Livewire-era leftover that was not
wired up after the React migration. CLAUDE.md still documents it as a
live feature.

Fix (Phase B): either wire it to the workspace (and add API endpoints
for it) or delete the service + its tests + the docs reference.

---

### DEAD-02 — `api.auth` middleware alias defined but never used — **MED / DeadEnd**

`bootstrap/app.php:17`:
`'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class`. No
route references it; `DualAuthentication` invokes `ApiKeyAuthentication`
directly as a fallback. Dead alias. Same pattern as the unused `role:`
middleware (AUTHZ-07 / DEAD-04 here).

Fix (Phase B): remove the alias, or wire it onto the MCP /
internal-only routes where API-key-only auth would be intentional.

---

### DEAD-03 — `GET /api/v1/prompts/{slug}` (legacy redirect) leaks cross-tenant owner-slug — **MED / Security + DeadEnd**

`PromptController::legacyRedirect()` `:142-170` performs
`Prompt::where('slug',$slug)->orderBy('created_at','asc')->first()`
**without `visibleTo()`** when the caller doesn't own a prompt with
that slug (`:154-158`). Returns a 301 with `redirect` body containing
the owner_slug and prompt_slug. Any authenticated user can therefore
enumerate global slugs and learn the owner of any private prompt
matching a guessed slug. Same TPL-02 / MCP-06 fail-open root cause, in
yet another surface. Pre-namespacing compatibility shim that probably
should not exist anymore.

Fix (Phase B): apply `Prompt::visibleTo($user)` to the fallback query
*or* delete the legacy route (clients have been on
`/prompts/{username}/{slug}` since Phase 7).

---

### DEAD-04 — `role:` middleware alias unused — **LOW / DeadEnd**

Reaffirms AUTHZ-07. Listed here for inventory.

---

### DEAD-05 — `GET /api/v1/mcp` route exists only to return 405 — **LOW / DeadEnd**

`routes/api.php:167` → `McpController::stream()` → unconditionally
returns `response('', 405)` (`McpController.php:106-111`). Laravel's
method-not-allowed handling already returns 405 for unmatched verbs on
a defined path. The explicit GET handler exists only to set an `Allow`
header — Laravel can do that too. Trivial; LOW.

Fix (Phase B): remove the route + method; let Laravel's default
405 handling cover it.

---

### DOC-10 — Sprint 0 recon corrections recorded — **(informational)**

The Sprint 0 reconnaissance over-counted in two places; Phase B should
use these measured values, not the recon estimates:

- "24 legacy files" → actually **9** files in `docs/legacy/`
  (3 plans, 6 specs; the recon counted by category not files).
- "PipelinesPage is a minimal stub" → actually an 11-line wrapper
  around the live `PipelinesTab` component (`PipelinesPage.jsx`,
  `resources/js/spa/components/settings/PipelinesTab.jsx`). Not dead;
  promoted to top-level page deliberately.
- "AiAssistantService used for diff summarization & prompt improvement"
  (recon) → **zero callers** (DEAD-01).

---

## Positives (no action)

- `docs/legacy/` has a clear obsolescence header
  (`docs/legacy/README.md:3-5`).
- `documentation/mcp-clients.md`, `documentation/install.md`,
  `documentation/architecture.md` are otherwise accurate against
  Sprint-1-through-5 measurements (modulo the counts).
- `docs/scheduling.md` accurately marks Phase 3 ("internal scheduler")
  as TBD.
- `PipelinesPage` is not the dead-stub the recon implied — it's a
  thin wrapper, intentional.

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| DOC-01 | High | Operational/Drift | README.md:125 (`[MIT](LICENSE)`); no LICENSE file |
| DOC-02 | Med | Drift | README.md:104; CLAUDE.md:19,351 |
| DOC-03 | Med | Drift | README.md:19,94; architecture.md:372; mcp-clients.md |
| DOC-04 | Med | Drift | claude-skill.md:8,42,50,54,61 |
| DOC-05 | Med | Drift | public/openapi.json (20 paths vs 93 routes) |
| DOC-06 | Low | Drift | public/openapi.json:6 |
| DOC-07 | Med | Drift | docs/2026-04-23-{oauth-refresh-tokens-plan,workspace-feature-sprints}.md |
| DOC-08 | Med | Process | (absent) SECURITY.md, CONTRIBUTING.md, CHANGELOG.md |
| DOC-09 | Low | Drift | README.md:121 |
| DEAD-01 | Med | DeadEnd | app/Services/AiAssistantService.php (0 callers) |
| DEAD-02 | Med | DeadEnd | bootstrap/app.php:17 (`api.auth` alias) |
| DEAD-03 | Med | Security/DeadEnd | PromptController.php:142-170; routes/api.php:84 |
| DEAD-04 | Low | DeadEnd | bootstrap/app.php:16 (`role:` alias) |
| DEAD-05 | Low | DeadEnd | routes/api.php:167; McpController.php:106-111 |

**Cross-cutting:** doc drift centers on (a) counts that should be CI-
generated and (b) hardcoded domain references that should be templates.
Together with INFRA-07 (no CI), there is no mechanism keeping docs
honest. Dead code (`AiAssistantService`, two unused middleware aliases,
the 405-only MCP GET route, the legacy global-slug redirect) is mostly
Livewire-era residue from the React migration. Phase B should bundle
doc regeneration + dead-code removal into one "post-migration cleanup"
sprint that runs after the security fixes ship, since some dead routes
(DEAD-03) also have security implications.

## Next

**Sprint 8 — Synthesis & Prioritized Backlog.** Consolidate all
findings from Sprints 0–7 into a single, deduplicated, severity-ranked
master backlog; cross-cutting themes; proposed Phase B remediation
sprint sequence; regression-test gap list. This is the hand-off
artifact that gates Phase B. Report-only.
