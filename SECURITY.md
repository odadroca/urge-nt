# Security Policy

## Reporting a Vulnerability

Please report security vulnerabilities **privately** — do not open a public
GitHub issue for security problems.

- Email: **acordado@tuta.com** with the subject line `SECURITY: URGE`.
- Include: affected version/commit, a description of the issue, reproduction
  steps, and the impact you observed.

You can expect an acknowledgement within **5 business days** and a remediation
plan or fix timeline once the report is triaged.

Please give us a reasonable window to ship a fix before any public disclosure.

## Supported Versions

URGE is self-hosted; security fixes target the `main` branch. Operators are
expected to deploy from `main`.

## Hardening Checklist for Operators

URGE ships secure-by-default, but a few settings must be set per deployment:

- `APP_DEBUG=false` in production (the default in `.env.example`).
- `APP_KEY` generated (`php artisan key:generate`) — this key encrypts
  sessions, LLM provider API keys, and stored result content. **Rotating or
  losing it makes encrypted data unrecoverable.**
- `SESSION_SECURE_COOKIE=true` behind HTTPS.
- `CORS_ALLOWED_ORIGINS` set to your SPA origin(s) in production.
- `CURL_SSL_VERIFY=true` (default) — keep TLS verification on for outbound
  LLM calls.
- Run behind HTTPS with HSTS (URGE emits the header when the request is
  secure or `APP_ENV=production`).
- Restrict who can reach `/docs` and `/openapi.json` if you don't want your
  API surface publicly enumerable.

## Cryptography & Data-at-Rest

- OAuth tokens, authorization codes, API keys, and OAuth client secrets are
  stored as SHA-256 hashes.
- LLM provider API keys and result content (`response_text`,
  `rendered_content`, `error_message`, `variables_used`) are encrypted with
  Laravel's `encrypted` cast (AES-256-GCM via `APP_KEY`).
- A non-reversible `response_hash` (SHA-256) is stored alongside the
  encrypted response for deduplication only.

A compromised `APP_KEY` decrypts all of the above — protect it like a root
credential.
