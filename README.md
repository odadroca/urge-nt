# URGE v2

Self-hosted prompt registry and version control system.

## What is URGE?

URGE is a **prompt memory layer** that sits behind any LLM. Instead of URGE calling LLMs, **LLMs call URGE** — pulling prompts, filling variables, resolving includes, and storing results back via API or MCP.

Two access patterns, one backend:
- **Humans** manage and curate prompts through a React SPA (primary UI) and Livewire 3 pages (settings, teams)
- **Machines** (any LLM) consume and contribute to the registry via REST API or MCP server

## Features

- **Prompt versioning** — immutable versions with auto-numbering, pin a specific version or default to latest
- **Version branching** — non-linear version history with named branches, independent version numbers per branch, create/delete/set-default
- **Template engine** — `{{variables}}` for substitution, `{{>slug}}` for recursive includes, circular reference detection
- **React SPA** — Browse, Canvas (graph visualization), and Workspace pages with slim icon-rail sidebar and mobile bottom tab bar
- **3-panel workspace** — editor, version sidebar, and results panel in a single screen
- **Live preview** — rendered preview with include resolution and variable fill from defaults
- **Visual composer** — drag-and-drop blocks (text, variable chips, include chips) via SortableJS
- **REST API** — full CRUD with Bearer token auth, rate limiting, OpenAPI 3.1 spec
- **OAuth 2.1** — PKCE (S256), scoped tokens (mcp:read, mcp:write, mcp:admin), GitHub as external provider, confidential client support with client_secret
- **MCP server** — Streamable HTTP (primary) and stdio (local) transports with 16 tools and 6 resources
- **6 LLM drivers** — OpenAI, Anthropic, Mistral, Gemini, Ollama, OpenRouter
- **Import/export** — Markdown with YAML frontmatter for prompts and results
- **Collections** — curated groupings of prompt versions, results, and nested collections (DAG) with ordering and public share links
- **Categories and tags** — organize prompts with color-coded categories and freeform tags
- **Version diff** — side-by-side comparison of any two versions
- **Result comparison** — compare 2-4 LLM responses side by side
- **Role-based access** — admin, editor, viewer roles (first user auto-admin)
- **User namespaces** — private prompts by default, GitHub-style `{username}/{slug}` URLs, visibility scoping across all surfaces
- **Teams** — create teams, invite members, share prompts collaboratively with team-based access control

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12 / PHP 8.3+ |
| Frontend (SPA) | React 19, React Query, @xyflow/react |
| Frontend (settings/teams) | Livewire 3, Alpine.js |
| Styling | Tailwind CSS 3.1 |
| Database | SQLite (default, configurable) |
| Build | Vite 7 |
| Testing | PHPUnit 11 (365 tests) |

## Quick Start

```bash
# Install dependencies
composer install && npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
touch database/database.sqlite
php artisan migrate

# (Optional) Load demo data
php artisan db:seed --class=DemoSeeder

# Build frontend
npm run build

# Start server
php artisan serve
# Visit http://127.0.0.1:8000
```

For development with HMR, queue worker, and log tailing:

```bash
composer dev
```

Register at `/register` — the first user automatically becomes admin.

## API Overview

All API endpoints are under `/api/v1/` and require Bearer token authentication (except health check).

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check |
| GET | `/prompts` | List prompts (filter: type, category, tag, search, scope) |
| POST | `/prompts` | Create prompt (private by default) |
| GET | `/prompts/{username}/{slug}` | Get prompt with active version |
| PATCH | `/prompts/{username}/{slug}` | Update prompt metadata |
| DELETE | `/prompts/{username}/{slug}` | Delete prompt (owner/admin only) |
| GET | `/prompts/{username}/{slug}/versions` | List versions |
| POST | `/prompts/{username}/{slug}/versions` | Create version |
| GET | `/prompts/{username}/{slug}/versions/{n}` | Get specific version |
| POST | `/prompts/{username}/{slug}/render` | Render template with variables |
| GET | `/prompts/{username}/{slug}/branches` | List branches |
| POST | `/prompts/{username}/{slug}/branches` | Create branch |
| GET | `/prompts/{username}/{slug}/results` | List results |
| POST | `/prompts/{username}/{slug}/results` | Store result |
| POST | `/prompts/{username}/{slug}/share` | Share with team |
| DELETE | `/prompts/{username}/{slug}/share/{team}` | Unshare from team |
| GET | `/results/{id}` | Get single result |
| PATCH | `/results/{id}` | Update rating/starred/notes |
| GET | `/results/starred` | List starred results across all prompts |
| GET | `/teams` | List user's teams |
| POST | `/teams` | Create team |
| GET | `/teams/{slug}` | Get team details |
| PATCH | `/teams/{slug}` | Update team |
| DELETE | `/teams/{slug}` | Delete team |
| POST | `/teams/{slug}/members` | Add member |
| DELETE | `/teams/{slug}/members/{user}` | Remove member |
| GET | `/collections` | List collections |
| POST | `/collections` | Create collection |
| GET | `/collections/{slug}` | Get collection (recursive nesting) |
| POST | `/collections/{slug}/items` | Add item (prompt_version, result, or collection) |

Full spec available at [`public/openapi.json`](public/openapi.json), importable as a CustomGPT Action.

## MCP Integration

URGE exposes an MCP server (protocol version 2025-06-18) with two transports. Both share the same tool dispatch layer.

**Verified MCP clients:** Claude.ai, Claude Desktop (OAuth via Dynamic Client Registration), Mistral Le Chat (OAuth via pre-registered confidential client), Claude Code (stdio, no auth).

**Claude.ai / Claude Desktop** (OAuth — just provide the URL, auth is automatic via Dynamic Client Registration):

```json
{
  "mcpServers": {
    "urge": {
      "url": "https://your-urge-instance.com/api/v1/mcp"
    }
  }
}
```

**Mistral Le Chat** (OAuth via pre-registered confidential client):

First create the client on your URGE instance:
```bash
php artisan oauth:create-client "Le Chat" \
  --redirect="https://callback.mistral.ai/v1/integrations_auth/oauth2_callback" \
  --confidential
```

Then in Le Chat, add URGE as an MCP integration with the generated `client_id` and `client_secret`. Le Chat discovers endpoints via `/.well-known/openid-configuration`.

**stdio transport** (for local dev — Claude Code on the same machine):

```json
{
  "mcpServers": {
    "urge": {
      "command": "php",
      "args": ["artisan", "urge:mcp-server", "--user=1"],
      "cwd": "/path/to/urge-v2"
    }
  }
}
```

Session state is managed via the `Mcp-Session-Id` header (set by the server on first response).

**Tools (16):** `get_prompt`, `list_prompts`, `render_prompt`, `save_version`, `store_result`, `get_results`, `update_result`, `delete_result`, `delete_prompt`, `share_prompt`, `list_teams`, `list_branches`, `create_branch`, `create_prompt`, `list_templates`, `run_template`

**Resources:** `urge://prompts`, `urge://prompts/{username}/{slug}`, `urge://prompts/{username}/{slug}/v/{n}`, `urge://prompts/{username}/{slug}/branches`, `urge://prompts/{username}/{slug}/branches/{branch}`, `urge://teams`

See [`documentation/mcp-clients.md`](documentation/mcp-clients.md) for complete setup guides (Claude, Le Chat, stdio) and [`documentation/claude-skill.md`](documentation/claude-skill.md) for API usage examples.

## Authentication

Triple-auth cascade: Sanctum sessions (SPA) → OAuth 2.1 tokens → API keys (`urge_` prefix).

**OAuth 2.1:**
- PKCE with S256 (required for public clients, optional for confidential)
- Confidential client support with `client_secret` (for Mistral Le Chat and similar)
- Dynamic Client Registration (RFC 7591) for Claude Desktop / Claude.ai
- GitHub as external identity provider
- Scopes: `mcp:read`, `mcp:write`, `mcp:admin` (enforced on OAuth tokens; API keys have full access)

**OAuth endpoints:**
| Endpoint | Description |
|----------|-------------|
| `GET/POST /oauth/authorize` | Consent page |
| `POST /oauth/token` | Code exchange (accepts `code_verifier` or `client_secret`) |
| `POST /oauth/register` | Dynamic Client Registration (RFC 7591) |
| `GET /oauth/github` | GitHub OAuth redirect |
| `GET /oauth/github/callback` | GitHub OAuth callback |

**Discovery endpoints:**
| Endpoint | Description |
|----------|-------------|
| `GET /.well-known/oauth-protected-resource` | RFC 9728 — protected resource metadata |
| `GET /.well-known/oauth-authorization-server` | RFC 8414 — authorization server metadata |
| `GET /.well-known/openid-configuration` | OIDC Discovery (required by Mistral Le Chat) |

## Testing

```bash
php artisan test    # 365 tests
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan urge:mcp-server` | Start stdio MCP server |
| `php artisan urge:import-v1 {path}` | Migrate data from URGE v1 SQLite database |
| `php artisan oauth:create-client {name}` | Create pre-registered OAuth client (supports `--redirect`, `--confidential`) |

## Documentation

- [`documentation/architecture.md`](documentation/architecture.md) — Data model, integration architecture, component hierarchy
- [`documentation/install.md`](documentation/install.md) — Installation and deployment guide
- [`documentation/mcp-clients.md`](documentation/mcp-clients.md) — MCP client setup guide (Claude, Mistral Le Chat, stdio)
- [`documentation/claude-skill.md`](documentation/claude-skill.md) — API reference for LLM integration
- [`public/openapi.json`](public/openapi.json) — OpenAPI 3.1 specification
- [`docs/superpowers/specs/2026-03-25-user-namespaces-design.md`](docs/superpowers/specs/2026-03-25-user-namespaces-design.md) — Namespaces & teams design spec
- [`docs/namespaces-sprints.md`](docs/namespaces-sprints.md) — Phase 7 sprint plan with ready-to-use prompts
- [`docs/superpowers/specs/2026-03-26-collection-visibility-design.md`](docs/superpowers/specs/2026-03-26-collection-visibility-design.md) — Collection visibility improvements design spec
- [`docs/superpowers/specs/2026-03-27-nested-collections-design.md`](docs/superpowers/specs/2026-03-27-nested-collections-design.md) — Nested collections (collections inside collections) design spec
