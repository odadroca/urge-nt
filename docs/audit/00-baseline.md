# Sprint 0 — Ground Truth & Harness

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).

> **Closing state (Phase B complete, 2026-05-20):** test suite **417 →
> 513 passing**; MCP tools **31** (unchanged); `composer audit` and
> `npm audit` **clean** (vite 7.3.3, commonmark 2.8.2+, Symfony 7.4.12+).
> All 4 CRIT + 18 HIGH findings fixed. See `docs/audit/pb-8-closure.md`.

Purpose: establish verifiable baselines so later audit sprints cite measured
facts rather than documentation claims.

---

## 1. Test Suite (measured)

| Metric | Value |
|---|---|
| Command | `php artisan test` |
| Result | **417 passed** (0 failed) |
| Assertions | 1169 |
| Duration | ~9.4s |
| `test_*` methods + `#[Test]` | 415 + 2 ≈ 417 |

**Drift confirmed (DOC-DRIFT-01):**

| Source | Claimed | Actual |
|---|---|---|
| `README.md:104` | 386 passing | **417** |
| `CLAUDE.md:19` (comment) | 410 tests | **417** |
| `CLAUDE.md:351` (status) | 410 tests passing | **417** |

All three documented test counts are wrong and mutually inconsistent.
Suite is currently **green**, so the audit baseline is a passing build.

> Setup note: `vendor/` and `.env` were absent on a fresh clone; required
> `composer install` + `cp .env.example .env` + `php artisan key:generate` +
> `touch database/database.sqlite` before tests would run. The documented
> quick-start in `CLAUDE.md` is accurate for this.

---

## 2. MCP Tools (measured)

Enumerated from `app/Services/McpToolHandler.php` (`'name' => '...'` entries).

- **Actual tool count: 31** (server-info `'name' => 'urge'` at `:25` excluded).
- Full list: add_channel, archive_version, create_branch, create_pipeline,
  create_prompt, delete_pipeline, delete_prompt, delete_result,
  evaluate_result, get_evaluation_prompt, get_evaluations, get_pipeline,
  get_prompt, get_results, list_branches, list_pipelines, list_prompts,
  list_providers, list_teams, pin_version, remove_channel, render_prompt,
  run_pipeline, run_prompt, save_version, share_prompt, store_evaluation,
  store_result, update_channel, update_pipeline, update_result.

**Drift confirmed (DOC-DRIFT-02):** `README.md`, `CLAUDE.md`,
`documentation/architecture.md`, `documentation/mcp-clients.md` all claim
**29 tools**. `pin_version` and `archive_version` are undocumented in the tool
catalog. Sprint 5 must verify scope mapping for all 31 (esp. the 2 new ones).

---

## 3. Structural Inventory (measured)

| Element | Count | Doc claim | Note |
|---|---|---|---|
| Migrations (`database/migrations`) | **38** | — (CLAUDE.md: "14 domain tables") | recon agent's "48" was wrong |
| Models (`app/Models`) | 21 | 21 | matches |
| Services (`app/Services/*.php` top-level) | **12** | "20" (recon) | recon over-counted (incl. subdirs) |
| Middleware | 5 | 5 | matches |
| Controllers (`app/Http/Controllers/**`) | 37 | — | |
| Route entries (`route:list`) | ~142 | — | full surface for Sprint 2/6 |
| SPA `console.*` calls | 27 | — | info-leak candidate (Sprint 4) |

OAuth refresh tokens: migration `create_oauth_refresh_tokens_table.php`
**exists** → feature is implemented, not merely planned. This **contradicts**
`docs/2026-04-23-oauth-refresh-tokens-plan.md` being read as "status unclear"
and the doc agent's MEDIUM-risk flag. Sprint 1 verifies behavior; Sprint 7
should mark the plan doc as delivered.

---

## 4. Dependency CVE Baseline

### npm audit — **5 vulnerabilities (3 high, 2 moderate)**

| Package | Severity | Advisory | Fix |
|---|---|---|---|
| `vite` 7.0.0–7.3.1 | **HIGH** | GHSA-4w7w-66w2-5vf9 (path traversal, optimized deps `.map`) | `npm audit fix` |
| `vite` | **HIGH** | GHSA-v2wj-q39q-566r (`server.fs.deny` bypass via queries) | `npm audit fix` |
| `vite` | **HIGH** | GHSA-p9ff-h696-f583 (arbitrary file read via dev-server WS) | `npm audit fix` |
| `postcss` <8.5.10 | MODERATE | GHSA-qx2v-qp2m-jg93 (XSS via unescaped `</style>`) | `npm audit fix` |
| `postcss` (picomatch path) | MODERATE | (transitive) | `npm audit fix` |

Vite advisories are **dev-server only** (not shipped to prod build) — relevant
to developer-machine threat model and CI. Still in scope (Aggressive appetite):
Sprint 6 to bump and re-test. postcss XSS warrants confirmation it can't reach
generated CSS.

### composer audit — **not run**

Blocked: "Composer plugins disabled for safety in non-interactive session …
No packages - skipping audit." Re-run in Sprint 1/6 with
`COMPOSER_ALLOW_SUPERUSER=1 composer audit` after a normal install so PHP-side
CVEs are captured (Laravel 12, Sanctum 4 stack).

---

## 5. Corrections to Recon Assumptions

The Phase-A plan inherits some recon-agent estimates that Sprint 0 now corrects;
later sprints must use these measured values:

- Test count: **417** (not 386/410/415).
- MCP tools: **31** (not 29).
- Migrations: **38** (not 48).
- Top-level services: **12** (not 20).
- Refresh tokens: **implemented** (migration present), not "status unclear".

---

## 6. Findings Opened in Sprint 0

| ID | Area | Sev | Type | Evidence | Recommended fix |
|---|---|---|---|---|---|
| DOC-DRIFT-01 | Docs | Med | Drift | `README.md:104`, `CLAUDE.md:19,351` | Reconcile to 417; automate count in CI (Sprint 6/7) |
| DOC-DRIFT-02 | Docs/MCP | Med | Drift | `McpToolHandler.php` (31 names) vs README/CLAUDE/architecture/mcp-clients | Document `pin_version`, `archive_version`; update "29"→"31" |
| DEP-01 | Build | High | Security | `npm audit` (vite ×3) | Upgrade vite; re-run suite + build (Sprint 6) |
| DEP-02 | Build | Med | Security | `npm audit` (postcss XSS) | Upgrade postcss; verify CSS-output exposure (Sprint 6) |
| HARNESS-01 | Tooling | Low | DeadEnd | `composer audit` blocked | Re-run with `COMPOSER_ALLOW_SUPERUSER=1` (Sprint 1/6) |
| DOC-DRIFT-03 | Docs | Low | Drift | refresh-token migration present | Mark `docs/2026-04-23-oauth-refresh-tokens-plan.md` delivered (Sprint 7) |
| HYGIENE-01 | Repo/Privacy | Med | Security | `git ls-files storage/` — `storage/logs/laravel.log` + 44 compiled views tracked; standard Laravel per-dir `.gitignore` files absent | **Fixed in Sprint 0** (exception to report-only): added ignore rules, untracked log + view cache. Log-in-VCS can leak runtime/PII; verify history in Sprint 6 |

---

## 7. Next

Sprint 1 — Authentication & OAuth 2.1 (report-only). Pending user review of
this baseline.
