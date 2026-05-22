# Changelog

All notable changes to URGE are documented here. The format is loosely based
on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Security (audit remediation — Phase B)

A full red-team audit (see `docs/audit/`) drove the following hardening:

- **Authorization (PB-1):** introduced a Laravel Policy layer
  (`Prompt`/`Result`/`Pipeline`) backed by a shared `AuthorizationService`
  used by both the REST API and the MCP tool handler. Closed cross-tenant
  IDORs on results, pipelines, channels, and evaluations; centralized
  API-key prompt-scope enforcement; applied `role:admin` middleware to
  admin routes. Removed the legacy `/api/v1/prompts/{slug}` redirect and the
  405-only `GET /api/v1/mcp` route.
- **OAuth 2.1 (PB-2):** redirect_uri allowlist enforced on every
  code-issuing path; throttling on `/oauth/token`, `/oauth/register`,
  `/oauth/revoke`; verified-email + provider-identity binding for GitHub
  login (no more email-based account takeover); atomic single-use token/code
  rotation; removed SSRF metadata-fetch; added RFC 7009 `/oauth/revoke`;
  access-token TTL lowered to 1 hour.
- **Template engine (PB-3):** include resolution is now deny-by-default
  (no null-user global slug fallback); global expansion + output-size
  budgets bound include amplification; scalar-only variable values;
  share links require an expiry and are throttled.
- **LLM drivers (PB-4):** SSRF guard on provider endpoints; Gemini API key
  moved to a header; upstream error bodies sanitized; result content
  encrypted at rest; per-user throttle on LLM-dispatching routes;
  prompt-size cap.
- **Transport & infra (PB-5):** `SecurityHeaders` middleware (CSP, HSTS,
  X-Frame-Options, etc.); env-driven CORS allowlist; self-hosted API
  reference (no CDN); secure `.env.example` defaults; CI workflow; deploy
  script with backup + health check + rollback.
- **Docs & cleanup (PB-6):** added `LICENSE`, `SECURITY.md`,
  `CONTRIBUTING.md`, this changelog; removed dead code
  (`AiAssistantService`, unused `api.auth` alias); reconciled documented
  test/tool counts; replaced hardcoded domains with placeholders.

### Notes

- 31 MCP tools (was documented as 29; `pin_version` and `archive_version`
  were undocumented).
- Test suite: 503 passing.
