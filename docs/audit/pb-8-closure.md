# PB-8 — Verification & Closure

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Phase B complete.** Audit closed.
**Suite:** **513 passing** (1351 assertions). `pint --test`, `composer
audit`, `npm audit` all clean.

Final Phase B sprint. Re-verifies the fixed Crit/High items, resolves the
one residual risk flagged in the PB-5 review (CSP vs inline scripts), and
records the closing state.

---

## 1. Residual-risk resolution — CSP nonce

The PB-5 review flagged that the strict `script-src 'self'` CSP was never
browser-verified. PB-8's HTTP smoke test confirmed the concern was real:

- The **React SPA shell** (`spa.blade.php`) has **zero inline scripts** —
  fully CSP-compliant out of the box (verified: `/app/*` serves external
  Vite bundles only). **The primary surface was never at risk.**
- The **Blade auth/public layouts** each carry one inline dark-mode
  bootstrap `<script>` — `script-src 'self'` *would* block these in a
  browser (dark-mode flash-prevention, login/register/share pages).

**Fix:** `SecurityHeaders` now generates a per-request nonce, shares it to
views before render, and advertises `script-src 'self' 'nonce-…'`. The
four inline scripts emit `nonce="{{ $cspNonce }}"`. Result: strict CSP
preserved (no `unsafe-inline`, no `unsafe-eval`) **and** the inline
scripts execute. Verified end-to-end: header nonce == rendered `<script>`
nonce within a single request; distinct per request.

The auth pages' `guest` layout boots Alpine but declares no `x-data`, so
Alpine evaluates nothing → no `unsafe-eval` needed. (If Alpine-driven
Blade pages — e.g. the `app.blade.php` toasts — are ever reactivated,
they'd need the Alpine CSP build or a scoped policy; the SPA does not use
Alpine.)

Locked in by `tests/Feature/CspNoncePb8Test.php` (4 tests).

---

## 2. Closing-state verification

| Gate | Result |
|---|---|
| `php artisan test` | **513 passed** (1351 assertions) |
| `vendor/bin/pint --test` | pass (repo is Pint-clean) |
| `composer audit` | No advisories |
| `npm audit` | 0 vulnerabilities |
| `npm run build` | green (vite 7.3.3, self-hosted Scalar) |
| HTTP smoke (`/up`, `/docs`, `/login`, `/app/browse`) | 200 / correct redirects, security headers + nonce present |

### Regression coverage by sprint (all green)

| Suite | Tests | Closes |
|---|---|---|
| `AuthorizationPb1Test` | 23 | AUTHZ-01..09 (REST) |
| `McpAuthorizationPb1Test` | 10 | MCP-01..04 |
| `OAuthPb2Test` | 15 | AUTH-01..11 |
| `TemplatePb3Test` | 12 | TPL-01..09 |
| `LlmPb4Test` | 14 | LLM-01..09 |
| `TransportPb5Test` | 12 | INFRA-01..11, MCP-05 |
| `DocsCleanupPb6Test` | 6 | DOC-01..09, DEAD-01/02 |
| `CspNoncePb8Test` | 4 | CSP nonce closure |

Plus the pre-existing 417-test baseline (now 417 + 96 = 513).

---

## 3. Audit outcome — finding ledger

**77 findings opened in Phase A. Status at closure:**

| Severity | Opened | Fixed | Accepted / Deferred |
|---|---|---|---|
| Critical | 4 | 4 | 0 |
| High | 18 | 18 | 0 |
| Medium | 32 | 31 | 1 |
| Low | 23 | 21 | 2 |

**All 4 CRIT and all 18 HIGH closed.**

### Accepted / deferred (with rationale)

- **AUTHZ-10 (Low)** — `LlmProviderController::index` returns `endpoint`
  to non-admins. Preserved for the SPA provider-picker UX; `api_key`/
  `settings` are not exposed. Accepted as design.
- **MCP-08 (Med)** — API keys bypass `mcp:*` scopes (documented design).
  The cross-tenant risk it amplified (MCP-01) is closed; an API key now
  only reaches the owner's own resources. Behavior left as-is; documented.
- **DOC-05 (Low, reclassified)** — full machine-parity OpenAPI for all 93
  routes. The spec now states its scope honestly and points at
  `routes/api.php`; routes are enforced in code regardless. Completeness
  task, no security value — left as a tracked follow-up.

### One item this environment could not execute

- **Live browser smoke-test under CSP.** This sandbox has no browser.
  PB-8 verified at the HTTP layer (status codes, headers, nonce
  matching, SPA shell inline-script-free, self-hosted Scalar bundle
  referenced). **Recommended before production:** load `/app/*`, `/docs`,
  and `/login` in a real browser (or add a Playwright CI step) and
  confirm no CSP violations in the console. The analysis says it will be
  clean — the SPA is external-bundle-only and the inline scripts are
  nonced — but it has not been visually confirmed.

---

## 4. Phase B deliverables (commits on `claude/audit-planning-ovwWc`)

```
PB-1  authorization layer (Policies + AuthorizationService)   abbf06b
PB-2  OAuth & GitHub identity                                 3224e2d
PB-3  template deny-by-default + DoS budgets                  842b1b2
PB-4  LLM driver hygiene                                      985a07b
PB-5  transport, deploy hardening & CI  + pint pass           07084dc / 39ac856
PB-6  docs reconciliation + dead-code removal                 529e396
PB-7  dependency upgrades                                     b9c9db3
PB-8  verification & closure                                  (this commit)
```

Reports: `docs/audit/00-baseline.md` … `08-summary.md` (Phase A) +
`pb-1`…`pb-8` (Phase B).

---

## 5. Recommended next steps (post-audit, out of scope)

- Browser/Playwright CSP smoke-test in CI (the one unverified item).
- Optional hardening carried in finding notes: DNS-rebind protection at
  the egress layer (UrlSafetyService is IP-literal only); Alpine CSP
  build if Blade `x-data` pages return; per-record KMS encryption if the
  threat model outgrows `APP_KEY`-tier crypto; full OpenAPI regeneration.
- Rotate any credentials that may have been exposed pre-audit
  (provider API keys, OAuth client secrets) — the leak *paths* are
  closed (LLM-02/03), but previously-leaked values should be cycled.

---

**Phase B complete. The four critical IDORs, the OAuth takeover chain,
the public fragment-exfil path, the LLM credential-exfil chain, and the
transport-layer gaps are all closed, each with regression coverage.**
