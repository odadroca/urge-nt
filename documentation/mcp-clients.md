# MCP Client Setup Guide

How to connect LLM clients to URGE's MCP server. URGE supports the Model Context Protocol (MCP) with Streamable HTTP transport (protocol version `2025-06-18`) and OAuth 2.1 authentication.

## MCP Endpoint

```
https://your-urge-instance.com/api/v1/mcp
```

Supports `POST` (JSON-RPC 2.0), `DELETE` (session termination). Sessions managed via `Mcp-Session-Id` header.

## Authentication Methods

URGE supports two OAuth client types depending on what the MCP client requires:

| Client Type | Auth Method | Registration | Used By |
|-------------|-------------|-------------|---------|
| Public | PKCE (S256) | Dynamic (RFC 7591) — client self-registers | Claude.ai, Claude Desktop |
| Confidential | `client_secret` | Pre-registered via artisan command | Mistral Le Chat |

Both types use the standard OAuth 2.1 authorization code flow. The difference is how the client authenticates at the token endpoint:
- **Public clients** prove identity with a PKCE code verifier
- **Confidential clients** prove identity with a client secret

API keys (`urge_...` prefix) also work as Bearer tokens for backward compatibility.

## Discovery Endpoints

MCP clients discover URGE's OAuth configuration via standard well-known endpoints:

| Endpoint | Standard | Used By |
|----------|----------|---------|
| `/.well-known/oauth-protected-resource` | RFC 9728 | All clients (initial 401 discovery) |
| `/.well-known/oauth-authorization-server` | RFC 8414 | Claude.ai, Claude Desktop |
| `/.well-known/openid-configuration` | OIDC Discovery | Mistral Le Chat |

All three return the same authorization server metadata, including `authorization_endpoint`, `token_endpoint`, `registration_endpoint`, and `code_challenge_methods_supported`.

---

## Claude.ai

**Type:** Public client with Dynamic Client Registration

**Setup:** Settings > Integrations > Add custom MCP server

**URL:**
```
https://your-urge-instance.com/api/v1/mcp
```

No credentials needed. Claude.ai handles the full OAuth flow automatically:
1. Sends `POST /api/v1/mcp` → receives 401 with `WWW-Authenticate` header
2. Fetches `/.well-known/oauth-protected-resource` → finds auth server URL
3. Fetches `/.well-known/oauth-authorization-server` → finds `registration_endpoint`
4. Registers itself via `POST /oauth/register` (Dynamic Client Registration, RFC 7591)
5. Redirects you to URGE's consent page → you approve
6. Exchanges authorization code + PKCE verifier for access token
7. Connects to MCP with the token

**Troubleshooting:**
- If tools don't appear after connecting, disconnect and reconnect the integration
- URGE must allow `https://claude.ai` as an Origin (handled automatically)

## Claude Desktop

**Type:** Public client with Dynamic Client Registration (same flow as Claude.ai)

**Config** (`claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "urge": {
      "url": "https://your-urge-instance.com/api/v1/mcp"
    }
  }
}
```

On first connection, Claude Desktop opens your browser to URGE's consent page. Approve to complete the OAuth flow.

## Mistral Le Chat

**Type:** Confidential client with pre-registered credentials

Le Chat differs from Claude in how it handles OAuth:
- Discovers auth metadata via `/.well-known/openid-configuration` (OIDC), not `/.well-known/oauth-authorization-server`
- Requires a pre-registered `client_id` and `client_secret` — does NOT use Dynamic Client Registration
- Sends `client_secret` at the token endpoint instead of PKCE `code_verifier`
- Uses `https://callback.mistral.ai/v1/integrations_auth/oauth2_callback` as the redirect URI

### Step 1: Create a confidential OAuth client on URGE

SSH into your URGE server and run:

```bash
php artisan oauth:create-client "Le Chat" \
  --redirect="https://callback.mistral.ai/v1/integrations_auth/oauth2_callback" \
  --confidential \
  --scope="mcp:read mcp:write"
```

Output:
```
OAuth client created:
  client_id:     xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
  client_secret: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
  ↑ Save this now — it cannot be retrieved again.
  name:          Le Chat
  redirect_uris: https://callback.mistral.ai/v1/integrations_auth/oauth2_callback
  auth_method:   client_secret_post
```

**Save the `client_id` and `client_secret` immediately.** The secret is hashed in the database and cannot be retrieved after creation.

### Step 2: Add URGE as a connector in Mistral

Via Mistral's API (using the Python SDK):

```python
from mistralai import Mistral

client = Mistral()

connector = client.beta.connectors.create(
    name="urge",
    server="https://your-urge-instance.com/api/v1/mcp",
    visibility="private",
    description="URGE prompt registry",
    auth_data={
        "client_id": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
        "client_secret": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    },
)
```

Or via Le Chat UI: add as a custom MCP integration with the server URL and credentials.

Reference: [Mistral Connectors Management](https://docs.mistral.ai/capabilities/connectors/management)

### Step 3: Authenticate

On first use, Le Chat redirects you to URGE's consent page at `/oauth/authorize`. Approve the requested scopes (`mcp:read`, `mcp:write`). Le Chat then exchanges the authorization code + client secret for an access token.

### Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| `Missing oauth2 metadata secrets in oauth callback` | CORS blocking registration/token requests, or client credentials not configured | Ensure URGE has `OAuthCors` middleware on OAuth endpoints. Verify `client_id` and `client_secret` are set in Le Chat's `auth_data`. |
| `Invalid state` | Redirect URI mismatch or malformed callback URL | Ensure the OAuth client's `redirect_uris` includes exactly `https://callback.mistral.ai/v1/integrations_auth/oauth2_callback`. |
| Tools not listed | Auth flow incomplete | Complete the OAuth consent flow first. Le Chat requires auth before listing tools. |
| 403 Origin not allowed | URGE blocking Le Chat's browser origin | URGE allows all HTTPS origins by default. Check `McpController::isAllowedOrigin()`. |

### Adding redirect URIs to an existing client

If you need to add a new redirect URI to an existing Le Chat client:

```bash
php artisan tinker --execute="
\$client = App\Models\OAuthClient::where('client_name', 'Le Chat')->first();
\$uris = \$client->redirect_uris;
\$uris[] = 'https://new-callback-url.example.com/callback';
\$client->redirect_uris = array_unique(\$uris);
\$client->save();
echo json_encode(\$client->redirect_uris, JSON_PRETTY_PRINT);
"
```

## Claude Code (HTTP — desktop, web, mobile)

**Type:** Public client with Dynamic Client Registration (same flow as Claude.ai)

Claude Code's MCP registry is **separate** from Claude.ai chat's Connectors. Adding URGE under Settings → Integrations / Connectors in Claude.ai chat makes it available in chat only — Claude Code (CLI, `claude.ai/code`, and the Claude Code mobile app) does not read that list. URGE must be registered with Claude Code as a distinct step.

### Recommended: add once via the desktop CLI with user scope

From a desktop terminal where the Claude Code CLI is installed and signed in to the same Anthropic account as your other devices:

```bash
claude mcp add --transport http --scope user urge https://your-urge-instance.com/api/v1/mcp
```

Then open any Claude Code session and run `/mcp` to trigger the OAuth consent flow. `--scope user` writes the entry to your account-level Claude Code config, so it propagates to `claude.ai/code` and the Claude Code mobile app on the same account.

Notes:
- URGE's Dynamic Client Registration (RFC 7591) means each Claude Code instance self-registers as a new OAuth client, so you may see the consent screen again the first time you open Claude Code on a different device.
- Don't confuse this with Claude.ai's Connectors UI — that path only configures chat, not Claude Code.
- For local development against URGE running on the same machine without OAuth, use the **Claude Code (stdio — local)** section below instead.

## Claude Code (stdio — local)

**Type:** No auth (local process, runs as a specific user)

For Claude Code or Claude Desktop running on the same machine as URGE:

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

The `--user=1` flag specifies which URGE user to act as (by ID). No OAuth or API key needed — the stdio transport communicates directly via stdin/stdout.

## Scopes

OAuth tokens can be scoped to limit tool access:

| Scope | Tools |
|-------|-------|
| `mcp:read` | `get_prompt`, `list_prompts`, `render_prompt`, `get_results`, `list_branches`, `list_teams`, `list_pipelines`, `get_pipeline`, `list_providers`, `get_evaluations`, `get_evaluation_prompt` |
| `mcp:write` | All read tools + `create_prompt`, `save_version`, `store_result`, `update_result`, `create_branch`, `share_prompt`, `run_pipeline`, `run_prompt`, `evaluate_result`, `store_evaluation`, `create_pipeline`, `update_pipeline`, `add_channel`, `update_channel` |
| `mcp:admin` | All write tools + `delete_prompt`, `delete_result`, `delete_pipeline`, `remove_channel` |

API keys (legacy `urge_...` tokens) bypass scope checks and have full access.

## Available Tools (29)

### Prompt Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `create_prompt` | Create a new prompt with optional initial content | write |
| `get_prompt` | Fetch prompt by slug with active version | read |
| `list_prompts` | List/search prompts (scope: mine/shared/all) | read |
| `render_prompt` | Render template with variable substitution | read |
| `save_version` | Create a new version of a prompt | write |
| `delete_prompt` | Soft-delete a prompt (owner/admin) | admin |

### Result Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `store_result` | Save an LLM response (version defaults to active, accepts rendered_content/variables_used) | write |
| `get_results` | Get results for a prompt | read |
| `update_result` | Update rating, starred, notes | write |
| `delete_result` | Delete a result permanently | admin |

### Evaluation Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `evaluate_result` | Server-side LLM-powered evaluation (uses configured provider) | write |
| `store_evaluation` | Client-side evaluation storage (free, no API cost) | write |
| `get_evaluation_prompt` | Get evaluation prompt template for client-side execution | read |
| `get_evaluations` | Get all evaluations for a result | read |

### Pipeline Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `list_pipelines` | List available pipelines | read |
| `get_pipeline` | Get pipeline details with channels | read |
| `run_pipeline` | Run a pipeline (server-side execution) | write |
| `create_pipeline` | Create a new pipeline | write |
| `update_pipeline` | Update pipeline metadata | write |
| `delete_pipeline` | Delete a pipeline | admin |

### Channel Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `add_channel` | Add a channel to a pipeline | write |
| `update_channel` | Update a channel in a pipeline | write |
| `remove_channel` | Remove a channel from a pipeline | admin |

### Provider Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `list_providers` | List configured LLM providers | read |
| `run_prompt` | Run a prompt against a provider | write |

### Branch Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `list_branches` | List branches for a prompt | read |
| `create_branch` | Create a new branch | write |

### Team Tools

| Tool | Description | Scope |
|------|-------------|-------|
| `list_teams` | List user's teams | read |
| `share_prompt` | Share a prompt with a team | write |

## Client-Side Execution

LLMs can fetch prompts and pipelines from URGE, run them natively (free, no API cost), and store results back. This avoids consuming URGE's configured LLM provider credits.

### Single Prompt Flow

```
1. get_prompt(slug, owner) → prompt content + variables
2. LLM executes the prompt natively
3. store_result(slug, owner, response_text, rendered_content?, variables_used?)
```

The `store_result` tool's `version` parameter is optional and defaults to the active version. You can also pass `rendered_content` and `variables_used` to document the exact input used.

### Pipeline Execution Flow

```
1. get_pipeline(slug, owner) → pipeline with channels (each has provider config + system prompt)
2. For each channel:
   a. get_prompt(slug, owner) → prompt content
   b. LLM runs the prompt with the channel's system prompt
   c. store_result(slug, owner, response_text, provider, model)
3. (Optional) evaluate_result(result_id) or store_evaluation(result_id, scores)
```

### Client-Side Evaluation Flow

```
1. get_evaluation_prompt(result_id?) → evaluation prompt template
2. LLM evaluates the result using the prompt
3. store_evaluation(result_id, scores, composite_score?, evaluator_provider?, evaluator_model?)
```

This is free (no API cost to URGE) since the LLM performs the evaluation natively. For server-side evaluation (uses URGE's configured provider), use `evaluate_result(result_id)` instead.

## Resources (6)

| URI | Description |
|-----|-------------|
| `urge://prompts` | All visible prompts (JSON) |
| `urge://prompts/{username}/{slug}` | Active version content |
| `urge://prompts/{username}/{slug}/v/{n}` | Specific version content |
| `urge://prompts/{username}/{slug}/branches` | List branches |
| `urge://prompts/{username}/{slug}/branches/{branch}` | Branch HEAD content |
| `urge://teams` | User's teams (JSON) |
