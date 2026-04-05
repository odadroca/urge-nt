# URGE v2 — Claude Skill Document

URGE is a prompt registry and version control system with user namespaces and team-based sharing. You can use the URGE API to fetch, render, and manage prompt templates.

## Base URL

```
https://urge-next.acordado.org/api/v1
```

## Authentication

All API requests (except health check) require a Bearer token:

```
Authorization: Bearer urge_YOUR_API_KEY
```

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
| POST | `/mcp` | MCP JSON-RPC endpoint |

Legacy URLs (`/prompts/{slug}`) redirect to the namespaced version automatically.

## MCP Integration

URGE also supports the Model Context Protocol (MCP). Connect Claude Desktop or Claude Code to URGE as an MCP server.

### Available MCP Tools

- **get_prompt** — Fetch a prompt by slug (+ optional `owner` for namespace resolution)
- **list_prompts** — List/search prompts (scope: mine/shared/team:{slug}/all)
- **render_prompt** — Render a template with variables
- **save_version** — Create a new version
- **store_result** — Save an LLM response
- **get_results** — Get results for a prompt
- **update_result** — Update result metadata (rating, starred, notes)
- **delete_result** — Delete a result
- **delete_prompt** — Delete a prompt (owner/admin only)
- **share_prompt** — Share a prompt with a team
- **list_teams** — List user's teams with member/prompt counts

### MCP Resources

- `urge://prompts` — Visible prompts as JSON (namespace-scoped)
- `urge://prompts/{username}/{slug}` — Active version content
- `urge://prompts/{username}/{slug}/v/{n}` — Specific version content
- `urge://teams` — User's teams as JSON

Legacy resource URIs (`urge://prompts/{slug}`) still work as fallbacks.

### Claude Desktop Configuration (SSE)

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "urge": {
      "url": "https://urge-next.acordado.org/api/v1/mcp",
      "headers": {
        "Authorization": "Bearer urge_YOUR_API_KEY"
      }
    }
  }
}
```

### Claude Code Configuration (stdio)

For local development, use the stdio transport:

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
