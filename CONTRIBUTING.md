# Contributing to URGE

Thanks for your interest in improving URGE.

## Development setup

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan test          # full suite
composer dev              # serve + Vite HMR
```

## Before opening a PR

The CI workflow (`.github/workflows/ci.yml`) runs on every PR. Match it
locally:

- **Tests:** `php artisan test` — must stay green. Add tests for any new
  behavior; security-relevant changes need a regression test.
- **Formatting:** `vendor/bin/pint` — the repo is Pint-clean; run it before
  committing.
- **Frontend build:** `npm run build` — must succeed.
- **Dependency audits:** `composer audit` and `npm audit` — don't introduce
  new High/Critical advisories.

## Conventions

- Backend: Laravel 12 / PHP 8.3. Authorization goes through the Policy layer
  (`app/Policies`) + `AuthorizationService`, shared by REST controllers and
  the MCP tool handler — don't hand-roll per-method ownership checks.
- Template rendering must always pass a `User` for include visibility; never
  resolve includes with a null user.
- LLM driver errors must be sanitized (`DriverErrorSanitizer`) — never echo
  upstream response bodies or exception messages verbatim.
- Frontend: React 19 SPA under `resources/js/spa`.

## Reporting security issues

See [SECURITY.md](SECURITY.md) — please do **not** file public issues for
vulnerabilities.
