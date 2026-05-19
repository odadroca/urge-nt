# Sprint 3 — Template Engine, Injection & DoS

**Date:** 2026-05-19
**Branch:** `claude/audit-planning-ovwWc`
**Status:** Report-only. No source changes (this file only).
**Suite at audit time:** 417 passing (Sprint 0 baseline).

Scope: `{{var}}` / `{{>slug}}` resolution, include recursion, share-link
delivery, collection nesting, public render surface, prompt-injection &
DoS vectors.

Files reviewed: `app/Services/TemplateEngine.php`,
`app/Services/CollectionNestingService.php`,
`app/Services/ShareLinkService.php`, `app/Models/CollectionShareLink.php`,
`app/Http/Controllers/ShareController.php`,
`app/Http/Controllers/Api/RenderController.php`,
`app/Http/Controllers/InternalApiController.php`,
`app/Services/PipelineService.php` (render callers),
`resources/views/public/**`, `routes/web.php`, `config/urge.php`.

Severity: Crit / High / Med / Low. Type: Security / Bug / DeadEnd / Drift.

---

## Findings

### TPL-01 — Public share page resolves includes with no user → cross-tenant private-fragment disclosure (unauthenticated) — **HIGH / Security**

`ShareController::renderCollectionItems()` calls
`$templateEngine->render($item->item->content, [])`
(`ShareController.php:84`) — **no `$user`**. With a null user,
`TemplateEngine::findPromptBySlug()` hits the **global fallback**
`Prompt::where('slug',$slug)->first()` (`TemplateEngine.php:124-125`),
ignoring `visibleTo`. So an **unauthenticated** `/share/{token}` page whose
collection contains a prompt_version with `{{>some-slug}}` resolves that
include against the *entire instance* by slug (first match) and prints the
content publicly (escaped, but fully disclosed). A collection owner can
deliberately embed another tenant's private fragment slug to exfiltrate it to
the public internet; or it happens inadvertently on slug collision.

Fix (Phase B): render share content with the collection owner's user
context (or a strict no-cross-tenant include mode for public surfaces);
forbid the global fallback entirely (see TPL-02).

---

### TPL-02 — `findPromptBySlug` global fallback: fail-open include scoping wherever `$user` is null — **HIGH / Security**

`TemplateEngine.php:112-126`: when `$user === null`, includes resolve via
`Prompt::where('slug',$slug)->first()` — **any tenant's prompt**. Null-user
callers found:

- `ShareController.php:84` (TPL-01, unauthenticated).
- `PipelineService.php:48-51` — **every pipeline run**
  (`render($version->content,$vals,$meta,null,…)`).
- `PipelineService.php:328` — channel `system_prompt` include resolution
  (`render($systemPrompt,[],null)`).

So pipeline execution (including the cross-tenant `runPipeline` of
**AUTHZ-04**) injects arbitrary tenants' private fragment content into LLM
calls and into stored Results. This is a design-level fail-open shared root
cause; the safe default must be deny.

Fix (Phase B): remove the global fallback; require an explicit user/owner on
every render path; pipelines must render as the pipeline owner.

---

### TPL-03 — Unvalidated variable value types → `preg_replace_callback` TypeError (500 DoS) — **MED / Bug**

`render()` returns `$variables[$name]` (`:58`) / `$mergedMetadata[$name]
['default']` (`:62`) directly into `preg_replace_callback`. Request input is
validated only as `'variables' => 'nullable|array'`
(`RenderController.php:21`) — **no per-value string constraint**. Sending
`{"variables":{"x":["a"]}}` (or a nested object, or array `default` in
metadata) makes the callback return a non-string → PHP `TypeError` →
unhandled 500 (RenderController only catches `InvalidArgumentException`,
`:46`). Cheap, repeatable error-path / DoS; also reachable via MCP
`render_prompt` and pipeline runs.

Fix (Phase B): validate variable values are scalars; cast/`(string)` with a
guard; catch `\Throwable` around render.

---

### TPL-04 — Include expansion has per-path depth limit but no total-expansion / output-size budget → billion-laughs DoS — **HIGH / Security**

`resolveIncludes()` enforces only per-chain `count($chain) >= $maxDepth`
(10) and same-chain circular detection (`TemplateEngine.php:88-95`). There is
**no cap on total expansions or output size**. A fragment that includes
another N times, nested to depth 10, expands ≈ Nᵈ times (e.g. 4¹⁰ ≈ 1M)
each appending `longText` content → CPU/memory exhaustion (classic
XML-bomb / billion-laughs). Sibling repetition is *not* circular, so the
existing guard does not help. Reachable via authenticated `/render`, MCP
`render_prompt`, pipeline runs, **and the unauthenticated share page**
(TPL-01) — hence High.

Fix (Phase B): global expansion counter + max rendered-size cap + max
total-nodes budget, enforced across the whole render (not per path).

---

### TPL-05 — Included fragment can inject variable defaults into parent prompts (prompt injection) — **MED / Security**

`render()` `:39-46` merges included prompts' `variable_metadata` as the base
(`array_merge($activeVersion->variable_metadata, $mergedMetadata)`), and the
variable substitution (`:60-63`) will use a metadata `default` when the
caller supplies no value (even in strict mode, since a present default
suppresses the "missing" path). A malicious shared fragment can therefore
introduce a `default` for a variable name the parent leaves unfilled and
**silently inject attacker-controlled text** into the rendered prompt fed to
the downstream LLM. Cross-tenant when combined with TPL-02.

Fix (Phase B): do not let included-fragment metadata supply defaults for
parent-scope variables, or clearly namespace/трust-tag include-derived
metadata; treat fragment defaults as inert unless explicitly opted in.

---

### TPL-06 — Public `/share/{token}`: no throttle, asymmetric work, non-expiring links — **MED / Security**

`routes/web.php:15-17` exposes `/share/{token}` unauthenticated with **no
`throttle`**. Token is 256-bit CSPRNG (not guessable — good), but each hit
runs `recordAccess()` (an `increment()` + `update()` = 2 writes,
`CollectionShareLink.php:43-47`) and a full recursive collection render with
include resolution (TPL-04). Tiny request → large server cost = asymmetric
DoS. Also `ShareLinkService::createLink` maps an unknown/`default`
`expiresIn` to `expires_at = null` ⇒ **link never expires**
(`ShareLinkService.php:19-25`); revocation only via delete.

Fix (Phase B): add `throttle` to the share route; debounce/async access
accounting; require an explicit expiry (or sane default); add revoke UX.

---

### TPL-07 — Collection render trusts DAG acyclicity; recursive depth helpers unmemoized — **LOW / Security**

`ShareController::renderCollectionItems` recurses by `$remainingDepth`
(`unlimited?100:5`) with **no visited-set** — it assumes the stored DAG is
acyclic. `CollectionNestingService` blocks cycle *creation*, but a cycle
introduced via import/direct DB (or before validation existed) makes render
recurse to depth 100 with heavy per-level eager loading.
`getMaxChildDepth`/`getDepthFromRoot` (`CollectionNestingService.php:69-110`)
recurse without memoization → exponential on wide DAGs during nesting
validation.

Fix (Phase B): visited-set in render recursion; memoize depth walks; cap
unlimited mode.

---

### TPL-08 — Unresolved include silently emitted as literal → enumeration oracle — **LOW / Security**

`resolveIncludes` returns the literal `{{>slug}}` when the prompt is not
found / not visible (`TemplateEngine.php:99-101`). Differential output
(expanded vs literal) reveals whether a slug exists/visible — a low-grade
enumeration oracle, and confusing operationally (no error surfaced).

Fix (Phase B): optional strict-include mode that errors; or a uniform
placeholder that doesn't distinguish "missing" from "denied".

---

### TPL-09 — Inconsistent `strict` default across surfaces; public page leaks variable schema — **LOW / Bug**

API `/render` defaults `strict=true` (`RenderController.php:36`); MCP
`render_prompt` uses `strict:true` (`McpToolHandler.php:1238`); but the
**public share** render uses the `render()` default `strict=false`
(`ShareController.php:84`), so unfilled `{{var}}` appear literally on the
public page — leaking the variable schema and producing broken public output.

Fix (Phase B): explicit, consistent strict policy per surface.

---

## Dependency note

DEP-03 (`league/commonmark` CVE-2026-33347, embed `allowed_domains` bypass):
no app code renders user content through CommonMark (`ImportExportService`
does regex frontmatter, the share page uses `nl2br(e())`). Reachability via
URGE surfaces appears **low** — confirm the dependency origin (likely
transitive) and bump in Sprint 6.

---

## Positives (no action)

- Share token = `bin2hex(random_bytes(32))` → 256-bit CSPRNG, **not
  predictable** (refutes the recon "predictable token" assumption).
- Same-chain circular-include detection works (`TemplateEngine.php:88`).
- Per-path max-depth enforced (`:93`).
- **No stored XSS on the public share page**: rendered prompt output is
  `{!! nl2br(e($entry->rendered)) !!}` (escaped *before* `nl2br`);
  `response_text`, notes, titles via `{{ }}` (Blade-escaped). Only `{!!`
  occurrence in `resources/views/` is that safe pattern.
- Variable/include regexes are simple & anchored — no ReDoS.
- `CollectionNestingService` blocks self-nest, BFS-cycle, and depth at
  creation time.
- `InternalApiController::variables/fragments` correctly scope by
  `Prompt::visibleTo($user)`.

---

## Findings Summary

| ID | Sev | Type | Evidence |
|---|---|---|---|
| TPL-01 | High | Security | ShareController.php:84; TemplateEngine.php:124-125 |
| TPL-02 | High | Security | TemplateEngine.php:112-126; PipelineService.php:48-51,328 |
| TPL-03 | Med | Bug | TemplateEngine.php:54-66; RenderController.php:21,46 |
| TPL-04 | High | Security | TemplateEngine.php:81-107 |
| TPL-05 | Med | Security | TemplateEngine.php:39-63 |
| TPL-06 | Med | Security | routes/web.php:15-17; ShareLinkService.php:19-25; CollectionShareLink.php:43-47 |
| TPL-07 | Low | Security | ShareController.php:58-92; CollectionNestingService.php:69-110 |
| TPL-08 | Low | Security | TemplateEngine.php:99-101 |
| TPL-09 | Low | Bug | RenderController.php:36; ShareController.php:84 |

**Cross-cutting:** the null-user global include fallback (TPL-01/TPL-02) is
the same fail-open theme as Sprint 2's IDORs and Sprint 1's redirect-uri
issues — *deny-by-default* is the missing principle. TPL-02 directly
amplifies AUTHZ-04 (cross-tenant `runPipeline`). TPL-04 is the most
serious single-request DoS in the audit so far.

## Next

Sprint 4 — LLM Drivers & Provider Management (report-only). Pending user
review.
