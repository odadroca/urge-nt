# PB-6 — Documentation regeneration + dead-code removal

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes DOC-01..09, DEAD-01, DEAD-02; confirms
DEAD-04/05 already resolved; closes DOC-DRIFT-01/02/03.
**Suite:** 503 → **509 passing** (6 new guard tests).

Sixth Phase B sprint — Themes G (drift / no enforcement loop) and H
(dead Livewire-era residue), plus the governance-file gaps.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **DOC-01** | High | Added `LICENSE` (MIT) — README claimed `[MIT](LICENSE)` but no file existed. |
| **DOC-02** | Med | Test count reconciled to **503** in `README.md` and `CLAUDE.md` (were 386 / 410). |
| **DOC-03** | Med | MCP tool count reconciled to **31** across README, CLAUDE.md, `documentation/architecture.md`, `documentation/mcp-clients.md`; added `pin_version` + `archive_version` to the mcp-clients tool table. |
| **DOC-04** | Med | `documentation/claude-skill.md` — all 14 hardcoded `urge-next.acordado.org` URLs replaced with `your-urge-instance.example.com` placeholder. |
| **DOC-05** | Med | OpenAPI `info.description` now states it covers the core public endpoints and points to `routes/api.php` for the full list. (Full machine-generated parity for all 93 routes is a documentation-completeness follow-up, not a security gap — graded honestly here rather than left implicit.) |
| **DOC-06** | Low | OpenAPI `info.version` `4.0.0` → `2.0.0` (matches "URGE v2" branding). |
| **DOC-07** | Med | Both in-repo plan docs (`docs/2026-04-23-oauth-refresh-tokens-plan.md`, `…workspace-feature-sprints.md`) now carry a **Status: DELIVERED** header pointing at the shipping code / PB-2. |
| **DOC-08** | Med | Added `SECURITY.md` (private disclosure path + operator hardening checklist + crypto notes), `CONTRIBUTING.md`, `CHANGELOG.md`. |
| **DOC-09** | Low | Removed Alpine.js from the README "Built With" list (it survives only in two Blade auth-layout toast components). |
| **DEAD-01** | Med | Deleted `app/Services/AiAssistantService.php` (zero callers — Livewire-era leftover); removed its description from `CLAUDE.md` and `documentation/architecture.md`. |
| **DEAD-02** | Med | Removed the unused `api.auth` middleware alias from `bootstrap/app.php`. |
| **DEAD-04** | Low | The `role:` alias is **not** dead — PB-1 wired `role:admin` onto the users + provider-mutation route groups. Kept; finding resolved by use. |
| **DEAD-05** | Low | The `GET /api/v1/mcp` 405-only route was already removed in PB-1. Confirmed. |

---

## Changes

### New files
- `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`, `CHANGELOG.md`
- `tests/Feature/DocsCleanupPb6Test.php` (6 guard tests)

### Removed
- `app/Services/AiAssistantService.php`
- `api.auth` alias in `bootstrap/app.php` (+ its now-unused import)

### Edited
- `README.md` — tool count 29→31, test count 386→503, dropped Alpine
  from Built With, OpenAPI link already pointed to `resources/openapi.json`
  (PB-5).
- `CLAUDE.md` — test 410→503, tools 29→31 (×2), replaced the
  `AiAssistantService` service-list entry with `AuthorizationService`,
  status line updated + audit pointer.
- `documentation/architecture.md` — tools 29→31 (×2), removed
  `AiAssistantService` from the service tree.
- `documentation/mcp-clients.md` — heading 29→31, added the two missing
  tools to the prompt-tools table.
- `documentation/claude-skill.md` — placeholder domain.
- `resources/openapi.json` — version 2.0.0, coverage note in description.
- `docs/2026-04-23-*.md` — DELIVERED status headers.
- `app/Http/Middleware/SecurityHeaders.php` — collapsed the identical
  `if/else` CSP branch flagged in the PB-5 review into one call.

### Decision points
- **CLAUDE.md is tracked despite a `.gitignore` entry** (it was committed
  before the ignore line was added; gitignore doesn't untrack). Since
  it's published, its drift was reconciled. Leaving the (ineffective)
  ignore line alone — removing it is cosmetic and out of scope.
- **MIT copyright holder** set to "URGE contributors" — avoids asserting
  a specific legal entity while satisfying the MIT clause and matching
  the README's stated license.
- **DOC-05 scope.** Hand-regenerating a 93-route OpenAPI document is a
  large documentation effort with no security value (the routes are
  enforced in code regardless). PB-6 makes the spec honest about its
  scope and fixes the misleading version/domain; full parity is left as
  a tracked completeness task.

---

## Verification

```
$ php artisan test
Tests:    509 passed (1342 assertions)
$ vendor/bin/pint --test   # pass
```

`tests/Feature/DocsCleanupPb6Test.php` asserts: the four governance
files exist; LICENSE is MIT and matches the README; `AiAssistantService`
is gone; the `api.auth` alias is removed; README counts reconciled; no
`acordado.org` domain in `claude-skill.md` / `openapi.json`.

---

## Next

PB-7 — dependency upgrades (DEP-01 vite, DEP-02 postcss, DEP-03
commonmark), then flip the CI audit steps back to blocking. Then PB-8
(verification & closure incl. the deferred browser smoke-test of the
SPA + /docs under the new CSP).
