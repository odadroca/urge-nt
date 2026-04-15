# URGE v2 — Claude Skill Document

URGE is a prompt registry and version control system with user namespaces and team-based sharing. You can use the URGE API to fetch, render, and manage prompt templates.

## Base URL

```
https://urge-next.acordado.org/api/v1
```

## Authentication

All API requests (except health check) require authentication. Three options:

**API key** (simplest):
```
Authorization: Bearer urge_YOUR_API_KEY
```

**OAuth 2.1 token — public client** (Claude.ai, Claude Desktop):
```
Authorization: Bearer <oauth_access_token>
```

OAuth discovery flow: send a request → receive 401 → follow `/.well-known/oauth-protected-resource` → `/.well-known/oauth-authorization-server` → register via `POST /oauth/register` (Dynamic Client Registration, RFC 7591) → authorize with PKCE (S256) → exchange code + `code_verifier` for token.

**OAuth 2.1 token — confidential client** (Mistral Le Chat):

Pre-register a client on the server: `php artisan oauth:create-client "Le Chat" --redirect="..." --confidential`. The client discovers endpoints via `/.well-known/openid-configuration` (OIDC Discovery) → authorize → exchange code + `client_secret` for token.

Scopes: `mcp:read`, `mcp:write`, `mcp:admin`.

## Namespaces

Prompts are scoped to users via `{username}/{slug}` URLs (like GitHub repos). Prompts are private by default and can be shared with teams.

## Quick Start

### 1. Check API health

```bash
curl https://urge-next.acordado.org/api/v1/health
```

### 2. List available prompts

```bash
# List your own prompts (default scope: all visible)
curl -H "Authorization: Bearer urge_YOUR_KEY" \
  https://urge-next.acordado.org/api/v1/prompts

# Filter by scope: mine, shared, all
curl -H "Authorization: Bearer urge_YOUR_KEY" \
  "https://urge-next.acordado.org/api/v1/prompts?scope=mine"
```

### 3. Get a specific prompt

```bash
curl -H "Authorization: Bearer urge_YOUR_KEY" \
  https://urge-next.acordado.org/api/v1/prompts/alexandre/my-prompt-slug
```

### 4. Render a prompt with variables

```bash
curl -X POST \
  -H "Authorization: Bearer urge_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"variables": {"name": "Claude", "task": "code review"}}' \
  https://urge-next.acordado.org/api/v1/prompts/alexandre/my-prompt-slug/render
```

### 5. Save a result

```bash
curl -X POST \
  -H "Authorization: Bearer urge_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"version": 1, "response_text": "The LLM output...", "provider_name": "Anthropic", "model_name": "claude-3.5-sonnet"}' \
  https://urge-next.acordado.org/api/v1/prompts/alexandre/my-prompt-slug/results
```

### 6. Manage teams

```bash
# List your teams
curl -H "Authorization: Bearer urge_YOUR_KEY" \
  https://urge-next.acordado.org/api/v1/teams

# Create a team
curl -X POST \
  -H "Authorization: Bearer urge_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "My Team"}' \
  https://urge-next.acordado.org/api/v1/teams

# Share a prompt with a team
curl -X POST \
  -H "Authorization: Bearer urge_YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"team_slug": "my-team"}' \
  https://urge-next.acordado.org/api/v1/prompts/alexandre/my-prompt-slug/share
```

## Available Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Health check (no auth) |
| GET | `/prompts` | List prompts (filter: type, category_id, tag, search, scope) |
| POST | `/prompts` | Create prompt (private by default) |
| GET | `/prompts/{username}/{slug}` | Get prompt with active version |
| PATCH | `/prompts/{username}/{slug}` | Update prompt metadata |
| DELETE | `/prompts/{username}/{slug}` | Delete prompt (owner/admin only) |
| GET | `/prompts/{username}/{slug}/versions` | List versions |
| POST | `/prompts/{username}/{slug}/versions` | Create version |
| GET | `/prompts/{username}/{slug}/versions/{n}` | Get specific version |
| POST | `/prompts/{username}/{slug}/render` | Render template with variables |
| GET | `/prompts/{username}/{slug}/results` | List results |
| POST | `/prompts/{username}/{slug}/results` | Store result |
| POST | `/prompts/{username}/{slug}/share` | Share with team |
| DELETE | `/prompts/{username}/{slug}/share/{team}` | Unshare from team |
| GET | `/results/starred` | List starred results across all prompts |
| GET | `/results/{id}` | Get single result |
| PATCH | `/results/{id}` | Update result (rating, starred, notes) |
| DELETE | `/results/{id}` | Delete result |
| GET | `/teams` | List user's teams |
| POST | `/teams` | Create team |
| GET | `/teams/{slug}` | Get team details |
| PATCH | `/teams/{slug}` | Update team |
| DELETE | `/teams/{slug}` | Delete team |
| POST | `/teams/{slug}/members` | Add member |
| DELETE | `/teams/{slug}/members/{user}` | Remove member |
| POST | `/mcp` | MCP Streamable HTTP endpoint (protocol 2025-06-18) |

Legacy URLs (`/prompts/{slug}`) redirect to the namespaced version automatically.

## MCP Integration

URGE exposes an MCP server (protocol version 2025-06-18) via Streamable HTTP transport (`POST /api/v1/mcp`) and stdio. Session state is managed via the `Mcp-Session-Id` header.

Verified MCP clients: Claude.ai, Claude Desktop (OAuth via Dynamic Client Registration), Mistral Le Chat (OAuth via confidential client), Claude Code (stdio, no auth).

### Available MCP Tools (16)

- **get_prompt** — Fetch a prompt by slug (+ optional `owner` for namespace resolution)
- **list_prompts** — List/search prompts (scope: mine/shared/team:{slug}/all)
- **render_prompt** — Render a template with variables
- **save_version** — Create a new version
- **create_prompt** — Create a new prompt with initial version
- **store_result** — Save an LLM response
- **get_results** — Get results for a prompt
- **update_result** — Update result metadata (rating, starred, notes)
- **delete_result** — Delete a result
- **delete_prompt** — Delete a prompt (owner/admin only)
- **share_prompt** — Share a prompt with a team
- **list_teams** — List user's teams with member/prompt counts
- **list_branches** — List branches for a prompt
- **create_branch** — Create a new branch
- **list_pipelines** — List available pipelines
- **run_pipeline** — Run a pipeline against a prompt

### MCP Resources (6)

- `urge://prompts` — Visible prompts as JSON (namespace-scoped)
- `urge://prompts/{username}/{slug}` — Active version content
- `urge://prompts/{username}/{slug}/v/{n}` — Specific version content
- `urge://prompts/{username}/{slug}/branches` — List branches for a prompt
- `urge://prompts/{username}/{slug}/branches/{branch}` — Branch HEAD content
- `urge://teams` — User's teams as JSON

Legacy resource URIs (`urge://prompts/{slug}`) still work as fallbacks.

### Claude.ai / Claude Desktop (OAuth — automatic)

Just provide the MCP server URL. Auth is handled automatically via OAuth Dynamic Client Registration (RFC 7591) with PKCE:

```json
{
  "mcpServers": {
    "urge": {
      "url": "https://urge-next.acordado.org/api/v1/mcp"
    }
  }
}
```

On first connection, Claude will redirect you to URGE to authorize access. No API key or manual client registration needed.

### Mistral Le Chat (OAuth — confidential client)

Le Chat requires a pre-registered confidential client. On your URGE server:

```bash
php artisan oauth:create-client "Le Chat" \
  --redirect="https://callback.mistral.ai/v1/integrations_auth/oauth2_callback" \
  --confidential
```

Then in Le Chat, add URGE as an MCP integration with the generated `client_id` and `client_secret`. Le Chat discovers endpoints via `/.well-known/openid-configuration`.

### Claude Code (stdio — local)

For local development, use the stdio transport (no auth needed):

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
