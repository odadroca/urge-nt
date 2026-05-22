# PB-7 — Dependency upgrades

**Date:** 2026-05-20
**Branch:** `claude/audit-planning-ovwWc`
**Status:** **Lands.** Closes DEP-01, DEP-02, DEP-03 + 8 newly-disclosed
Symfony CVEs. CI audit gates flipped back to **blocking**.
**Suite:** 509 passing (unchanged). `npm audit` and `composer audit` both
**clean**.

Seventh Phase B sprint — Theme G's dependency leg.

---

## Findings closed

| ID | Sev | Closed by |
|---|---|---|
| **DEP-01** | High | `npm audit fix` upgraded **vite 7.0.x → 7.3.3** (patches path-traversal `.map`, `server.fs.deny` bypass, dev-server WebSocket arbitrary file read) and **picomatch** (method-injection + ReDoS). |
| **DEP-02** | Med | Same `npm audit fix` resolved the postcss/transitive advisories. `npm audit` → **0 vulnerabilities**. |
| **DEP-03** | Med | `composer update league/commonmark` → 2.8.2+ (patches CVE-2026-33347 embed `allowed_domains` bypass). |

## Additional advisories cleared (newly disclosed during this sprint)

`composer audit` surfaced **8 fresh Symfony CVEs (published 2026-05-20)**
in transitive components below 7.4.12 — not in the original audit because
they didn't exist when Sprint 0 ran. All cleared by
`composer update "symfony/*" --with-all-dependencies`:

| Package | CVE | Summary |
|---|---|---|
| symfony/http-kernel | CVE-2026-45075 | HEAD bypasses `methods:['GET']` filter in `#[IsGranted]` etc. |
| symfony/mailer | CVE-2026-45068 | Argument injection in SendmailTransport |
| symfony/mime | CVE-2026-45070 | Email header injection via mime param names |
| symfony/mime | CVE-2026-45067 | Header / SMTP injection via CRLF in `Address` |
| symfony/routing | CVE-2026-45065 | UrlGenerator off-site `//host` injection |
| symfony/yaml | CVE-2026-45304 | "Billion laughs" via recursive collection aliases |
| symfony/yaml | CVE-2026-45305 | ReDoS in `Parser::cleanup()` regex |
| symfony/yaml | CVE-2026-45133 | Stack exhaustion via unbounded nested-block recursion |

Several of these are directly relevant to URGE's surface: the routing
off-site-injection CVE (we generate redirect URLs in the OAuth flow), the
http-kernel HEAD bypass (we use signed routes for email verification), and
the mailer/mime injection set (password-reset mail). Patching to Symfony
7.4.12 / 8.0.x closes them.

---

## Changes

- `composer.lock` — `league/commonmark` 2.8.1 → 2.8.2+; Symfony
  components bumped to patched versions (http-kernel/mailer/mime/routing
  → 7.4.12, yaml patched, plus consistency upgrades of clock, console,
  string, translation, polyfills, etc.). **No `composer.json` constraint
  changes** — all within Laravel 12's existing requirements.
- `package-lock.json` — vite → 7.3.3, picomatch patched, transitive
  postcss chain updated. **No `package.json` changes.**
- `.github/workflows/ci.yml` — removed `continue-on-error` from both the
  `composer audit` and `npm audit` steps; they are now **blocking**
  gates (the whole point of INFRA-07, achievable now that the tree is
  clean).

---

## Decision points

- **Lockfile-only changes.** Both `npm audit fix` (non-`--force`) and the
  composer updates resolved entirely within the existing version
  constraints — no manifest edits, no major-version forcing. Lower
  regression risk; the test suite + build confirm behavior is unchanged.
- **Mixed Symfony 7.4 / 8.0 components.** `composer update "symfony/*"`
  pulled a few components (clock, css-selector, event-dispatcher, string,
  translation) to 8.0.x while the core HTTP stack stayed on patched
  7.4.12. Composer's solver only does this when Laravel 12's constraints
  permit it; the 509-test suite passing end-to-end is the validation.
- **Scope creep, accepted.** The 8 Symfony CVEs weren't in the original
  backlog (they were disclosed today). Leaving them unpatched while
  flipping `composer audit` to blocking would make CI red on the next
  run — and several are genuinely relevant to URGE's auth/mail surface.
  Patching them is the correct call for "make the audit gate green and
  meaningful."

---

## Verification

```
$ npm audit                       # found 0 vulnerabilities
$ composer audit --no-interaction # No security vulnerability advisories found.
$ php artisan test                # 509 passed (1342 assertions)
$ npm run build                   # built in ~46s (vite 7.3.3)
```

CI `composer audit` + `npm audit --audit-level=high` steps are now
blocking and will pass on this state.

---

## Next

PB-8 — verification & closure: full adversarial re-test of fixed items,
refresh `docs/audit/00-baseline.md` to the closing state (509 tests,
clean audits), and the **manual browser smoke-test of the SPA + `/docs`
under the PB-5 CSP** (the one item this sandbox can't execute).
