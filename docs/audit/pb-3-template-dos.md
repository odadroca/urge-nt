# PB-3 — Template engine deny-by-default + DoS budgets

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes all 9 template/share findings (TPL-01..09).
**Suite:** 465 → **477 passing** (12 new regression tests).

This is the third remediation sprint of Phase B. It implements Themes B
(deny-by-default include scoping) and D (DoS amplification budgets) from
`docs/audit/08-summary.md`: the null-user global slug fallback is gone,
every render path threads a user, sibling-fanout amplification is bounded
by a global expansion counter + output-size budget, variable values must
be scalar, include-derived metadata can no longer inject defaults into
parent scope, and `/share/{token}` is throttled with non-null expiries
required.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **TPL-01** | High | `ShareController::show` now passes the collection owner to `TemplateEngine::render`; nested-collection recursion does the same. With `TemplateEngine::findPromptBySlug` deny-by-default on null user, the public share page can no longer exfiltrate cross-tenant private fragments via `{{>slug}}`. |
| **TPL-02** | High | `TemplateEngine::findPromptBySlug` returns null when `$user` is null (was: global slug lookup). `PipelineService::run` and `resolveSystemPrompt` now pass the invoking user — every pipeline run, including channel system prompts, resolves includes within that user's visibility. |
| **TPL-03** | Med | `TemplateEngine::render` validates that every variable value is scalar (or null) up-front; non-scalars raise `InvalidArgumentException`. `RenderController` catches this and returns 422 instead of the pre-PB-3 uncaught TypeError 500. |
| **TPL-04** | High | New `urge.max_include_expansions` (default 500) and `urge.max_render_bytes` (default 5 MiB) budgets enforced inside `resolveIncludes` and verified again post-substitution. Sibling repetition no longer multiplies unchecked — billion-laughs amplification stops with a 422-mapped `RuntimeException`. |
| **TPL-05** | Med | Metadata-driven defaults are now sourced **only** from the caller-supplied `$metadata` argument. Included fragments no longer contribute defaults to the parent's substitution map; a malicious shared fragment cannot inject `default` text into a parent prompt's unfilled `{{var}}`. |
| **TPL-06** | Med | `ShareLinkService::createLink` throws `InvalidArgumentException` when `expiresIn` is null/unknown (was: silently → `expires_at = null` → never expires). `ShareLinkController::store` validates `expires_in` as `required|in:1h,24h,7d,30d`. `routes/web.php` puts `throttle:30,1` on `/share/{token}`. |
| **TPL-07** | Low | `ShareController::renderCollectionItems` carries a visited-set keyed by `collection_id`; a DAG cycle introduced outside `CollectionNestingService` (import, direct DB) is short-circuited instead of recursing to the configured `max_collection_depth` (or 100 in unlimited mode). |
| **TPL-08** | Low | New `strictIncludes` mode on `TemplateEngine::render` raises on unresolved includes for callers that want it (eg. CI render checks). Default behavior preserved (silent literal) — but with TPL-01/02 in place, "unresolved" no longer differentially leaks "exists but not yours" vs "does not exist": both routes through `findPromptBySlug` now return null indistinguishably. |
| **TPL-09** | Low | The render-engine no longer silently substitutes from included-fragment metadata (TPL-05), so the public share page no longer fills `{{var}}` from another tenant's fragment defaults. Strict-mode policy per surface stays as-is (API render strict-by-default, share page non-strict so literals remain visible to the reader). |

---

## Architecture

### Modified files

- `app/Services/TemplateEngine.php` — significant rewrite.
  - `render()` signature gains `bool $strictIncludes = false`.
  - `validateVariableValues()` rejects non-scalar values pre-render.
  - State carrier (`expansions`, `bytes`, max-of-each) threaded through
    `resolveIncludes` for the global budget guard.
  - Metadata merge from included fragments removed; only caller-supplied
    metadata supplies defaults.
  - `findPromptBySlug($slug, ?$user)` returns null when `$user` is null;
    no more global fallback.
- `app/Services/PipelineService.php`
  - `run()` reorders `User::find($userId)` before the content render
    so it's threaded through `TemplateEngine::render`.
  - `resolveSystemPrompt($channel)` → `resolveSystemPrompt($channel, ?User $user)`;
    both call sites updated.
- `app/Http/Controllers/ShareController.php`
  - Resolves the collection owner and threads it through render +
    nested recursion.
  - Visited-set guards collection-DAG cycles.
  - Removed two latent broken eager-load attempts
    (`pluck('item')->load(...)` was a no-op since `pluck` returns a
    `Support\Collection`); replaced by lazy loading inside the Blade
    partial, which already works.
- `app/Services/ShareLinkService.php` — `match` falls through to
  `InvalidArgumentException` when `expiresIn` is null/unknown.
- `app/Http/Controllers/Api/ShareLinkController.php` — `expires_in`
  now `required|in:1h,24h,7d,30d`.
- `app/Http/Controllers/Api/RenderController.php` — catches
  `RuntimeException` (include-budget / cycle / depth) → 422.
- `routes/web.php` — `throttle:30,1` on `/share/{token}`.
- `config/urge.php` — two new knobs: `max_include_expansions`,
  `max_render_bytes`.

### Decision points

- **Strict-includes flag is opt-in.** Default behavior remains "emit
  literal `{{>slug}}` if not found/visible" because (a) it's backward-
  compatible, and (b) TPL-08's enumeration oracle is closed by the
  deny-by-default of TPL-02: both "not found" and "not visible" now
  return null indistinguishably. The flag is available for future
  surfaces that want noisy failure.
- **Caller-only metadata defaults.** Fragments can still declare their
  own `variable_metadata`, but it only affects rendering of *that
  fragment* in isolation — never the parent scope. Closing TPL-05 means
  parent prompts must supply their own metadata for their own variables,
  which matches the principle of least surprise.
- **Removed broken eager-load.** The `pluck()->load()` pattern in
  `ShareController` was a no-op (`pluck` on an Eloquent collection
  returns `Support\Collection`, which has no `load` method). It would
  have errored on the first invocation that hit either branch. Tests
  before PB-3 didn't put `prompt_version` items into shared
  collections, which is why this slept. New test
  `test_share_page_does_not_leak_other_tenants_private_fragments`
  surfaced it; switched to lazy loading inside the Blade partial.
- **`/share/{token}` throttle = 30/min/IP.** Generous enough for
  legitimate browsing on shared documents (CDN/preview crawlers etc.)
  but firmly caps attacker amplification.

---

## Test coverage added

`tests/Feature/TemplatePb3Test.php` (12 tests):

- **TPL-01 / TPL-02:** null-user render does not fall back to global
  slug; unauthorized user cannot resolve another tenant's fragment;
  public share page does not leak Bob's private fragment when Alice's
  collection includes its slug.
- **TPL-03:** array variable value raises `InvalidArgumentException`
  at engine level; render API returns 422.
- **TPL-04:** sibling-fanout layout (30 top-level × tri-fanout fragment)
  exceeds the expansion budget; oversize single fragment exceeds the
  size budget.
- **TPL-05:** parent's `{{secret_inj}}` stays missing even though an
  included fragment declares a `default: INJECTED` for that name.
- **TPL-06:** share-link API rejects missing `expires_in` with 422;
  service rejects null expiry; `/share/{token}` returns 429 after 30
  hits in a minute.
- **TPL-07:** collection cycle via direct DB insert no longer hangs;
  share page responds 200.

---

## Verification

```
$ php artisan test
Tests:    477 passed (1271 assertions)
Duration: ~10s
```

Zero regressions in the existing 465-test suite (PB-1 + PB-2 + earlier).
Two pre-existing tests needed light updates documented in-line: a
circular-include test expecting `500` now expects `422` (input-shape
problem, not server bug); a nested-collection share test now passes an
explicit `expires_in`.

---

## Next

PB-4 — LLM driver hygiene (Theme C). Closes LLM-01..11: endpoint
allowlist + SSRF guard with bearer protection, Gemini key via header,
upstream-body echo sanitization, per-user throttle on LLM dispatch,
pre-dispatch prompt-size cap, encrypted-at-rest Result columns, and
SPA console-error redaction.
