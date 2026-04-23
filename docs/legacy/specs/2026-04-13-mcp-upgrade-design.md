# MCP Server Upgrade Design

## Context

URGE's MCP server uses protocol version `2024-11-05` with the deprecated HTTP+SSE transport and static API key authentication. The MCP spec has evolved: SSE transport is deprecated in favor of Streamable HTTP (`2025-03-26`), and OAuth 2.1 with PKCE is the recommended auth mechanism (`2025-06-18`). This upgrade brings URGE's MCP server to full spec compliance with no gaps.

## Decision

Full spec compliance: Streamable HTTP transport, OAuth 2.1 Authorization Server (URGE-native + GitHub), discovery endpoints, session management, scope enforcement, backward compatibility with existing API keys.

## 1. Streamable HTTP Transport

Replace the current dual-endpoint SSE transport (`POST /mcp` handle + `GET /mcp` stream with cache-polling) with the Streamable HTTP spec.

### Single endpoint: `POST /api/v1/mcp`

- Receives JSON-RPC 2.0 requests from clients
- Responds with `Content-Type: application/json` for simple responses
- May respond with `Content-Type: text/event-stream` for streaming tool results (future)
- No more separate SSE stream endpoint or cache-based session polling

### Optional `GET /api/v1/mcp`

- Server-initiated notifications via SSE stream
- Returns `405 Method Not Allowed` initially (no server-initiated messages needed yet)

### `DELETE /api/v1/mcp`

- Client terminates session
- Server cleans up session data from cache

### Session management

- Server generates `Mcp-Session-Id` on `initialize` response
- Client includes it on all subsequent requests
- Sessions stored in Laravel cache with TTL

### Protocol headers

- Client sends `Mcp-Protocol-Version: 2025-06-18` on all requests after initialization
- Server validates and includes in responses

### Security

- Validate `Origin` header on all requests
- Reject requests with unexpected origins

### stdio transport

Unchanged — already works, no auth or transport changes needed.

## 2. OAuth 2.1 Authorization Server

URGE acts as its own OAuth 2.1 Authorization Server. GitHub supported as external identity provider.

### Database tables

**`oauth_authorization_codes`:**
- `id`, `code` (string, hashed), `client_id` (string), `user_id` (FK), `redirect_uri` (string), `scope` (string), `code_challenge` (string), `code_challenge_method` (string, always `S256`), `expires_at` (datetime), `timestamps`

**`oauth_access_tokens`:**
- `id`, `token` (string, hashed), `user_id` (FK), `client_id` (string), `scope` (string), `expires_at` (datetime), `timestamps`

No refresh tokens — MCP clients re-authorize when tokens expire.

### OAuth endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/oauth/authorize` | GET | Show authorization consent page |
| `/oauth/authorize` | POST | User approves, generates auth code, redirects to client |
| `/oauth/token` | POST | Token exchange: auth code + PKCE verifier → access token |
| `/oauth/github` | GET | Initiate GitHub OAuth flow |
| `/oauth/github/callback` | GET | GitHub callback → create/match user → issue URGE token |

### PKCE (mandatory)

- Clients MUST use `S256` code challenge method
- Server stores `code_challenge` with authorization code
- Server validates `code_verifier` during token exchange
- Reject if `code_challenge_methods_supported` check fails

### Resource Indicators (RFC 8707)

- Client includes `resource` parameter in authorization and token requests
- Value is the URGE MCP endpoint URL (e.g., `https://urge.acordado.org/api/v1/mcp`)
- Server validates it matches

### Client registration

Two modes:
1. **Client ID Metadata Documents** — unregistered clients provide a `client_id` URL pointing to their metadata JSON document (MCP spec preferred approach)
2. **Pre-registered** — admin registers clients in URGE settings (future, not in this phase)

For Client ID Metadata Documents: server fetches the URL provided as `client_id`, validates it's a well-formed metadata document with `redirect_uris`, and uses those for redirect validation.

## 3. Discovery & Metadata Endpoints

### Protected Resource Metadata (RFC 9728)

`GET /.well-known/oauth-protected-resource`

```json
{
  "resource": "https://urge.acordado.org/api/v1/mcp",
  "authorization_servers": ["https://urge.acordado.org"],
  "scopes_supported": ["mcp:read", "mcp:write", "mcp:admin"],
  "bearer_methods_supported": ["header"]
}
```

### Authorization Server Metadata (RFC 8414)

`GET /.well-known/oauth-authorization-server`

```json
{
  "issuer": "https://urge.acordado.org",
  "authorization_endpoint": "https://urge.acordado.org/oauth/authorize",
  "token_endpoint": "https://urge.acordado.org/oauth/token",
  "scopes_supported": ["mcp:read", "mcp:write", "mcp:admin"],
  "response_types_supported": ["code"],
  "grant_types_supported": ["authorization_code"],
  "code_challenge_methods_supported": ["S256"]
}
```

Both endpoints use `APP_URL` from environment for the base URL.

### 401 Response

When MCP endpoint receives an unauthenticated request:

```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Bearer resource_metadata="https://urge.acordado.org/.well-known/oauth-protected-resource"
```

## 4. Scopes

| Scope | Tools allowed | Resources |
|-------|--------------|-----------|
| `mcp:read` | `get_prompt`, `list_prompts`, `render_prompt`, `get_results`, `list_branches`, `list_teams` | All resources |
| `mcp:write` | read + `save_version`, `store_result`, `update_result`, `create_branch`, `share_prompt` | All resources |
| `mcp:admin` | write + `delete_prompt`, `delete_result` | All resources |

API keys (legacy) have no scope restriction — full access as today.

Scope enforcement: `McpToolHandler` checks the token's scope before dispatching tools. Only applies to OAuth tokens, not API keys.

## 5. Auth Flow

### MCP client connection (OAuth)

1. Client `POST /api/v1/mcp` with `initialize` — no token
2. Server returns `401` with `WWW-Authenticate` header
3. Client discovers endpoints via `/.well-known/` chain
4. Client opens browser → `/oauth/authorize?client_id=...&redirect_uri=...&code_challenge=...&code_challenge_method=S256&scope=mcp:write&resource=...`
5. User logs in (if needed) → sees consent page → approves
6. Server redirects to `redirect_uri` with `code`
7. Client `POST /oauth/token` with `code`, `code_verifier`, `redirect_uri`, `client_id`
8. Server validates PKCE, issues access token
9. Client retries `POST /api/v1/mcp` with `Authorization: Bearer <oauth_token>`
10. Server validates token, returns `initialize` response with `Mcp-Session-Id`

### GitHub OAuth flow

1. User clicks "Sign in with GitHub" on consent page (or client initiates directly)
2. `GET /oauth/github` → redirects to GitHub OAuth with URGE's GitHub app credentials
3. GitHub callback → URGE creates or matches user by GitHub email
4. URGE issues OAuth access token for the matched user
5. Redirects back to MCP client's `redirect_uri` with authorization code (same flow as above, but user identity came from GitHub)

### Backward compatibility

| Token source | Validation | Where used |
|---|---|---|
| URGE API key (`urge_...`) | SHA-256 hash lookup in `api_keys` | REST API, CustomGPT Actions, legacy MCP |
| OAuth access token | Hash lookup in `oauth_access_tokens` | New MCP clients |
| Sanctum session cookie | Laravel session | React SPA |

`DualAuthentication` middleware becomes triple-auth: try Sanctum session → try OAuth token → try API key. First match wins.

## 6. Files

### New files

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_oauth_tables.php` | `oauth_authorization_codes` + `oauth_access_tokens` |
| `app/Models/OAuthAuthorizationCode.php` | Auth code model |
| `app/Models/OAuthAccessToken.php` | Access token model |
| `app/Http/Controllers/OAuthController.php` | `/oauth/authorize` (GET+POST), `/oauth/token` |
| `app/Http/Controllers/OAuthGitHubController.php` | GitHub OAuth flow |
| `app/Http/Controllers/WellKnownController.php` | `/.well-known/` discovery endpoints |
| `app/Services/OAuthService.php` | Code generation, PKCE validation, token issuance, scope checking |
| `resources/views/oauth/authorize.blade.php` | Authorization consent page |

### Modified files

| File | Change |
|------|--------|
| `app/Http/Controllers/McpController.php` | Rewrite: Streamable HTTP, session management, `Mcp-Protocol-Version`, `Origin` validation, `WWW-Authenticate` on 401 |
| `app/Http/Middleware/DualAuthentication.php` | Extend: Sanctum → OAuth token → API key |
| `app/Services/McpToolHandler.php` | Scope checking for OAuth tokens, bump protocol version to `2025-06-18` |
| `routes/web.php` | OAuth routes (`/oauth/*`), well-known routes |
| `routes/api.php` | Add DELETE method for MCP endpoint |
| `config/urge.php` | OAuth config: token TTL, GitHub credentials, scopes |
| `.env.example` | `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `OAUTH_TOKEN_TTL` |

### Unchanged

- `app/Console/Commands/McpServerCommand.php` — stdio transport stays as-is
- Tool and resource logic in `McpToolHandler` — only scope gating added
- All existing API key infrastructure
- All existing REST API endpoints

## 7. Configuration

Add to `config/urge.php`:

```php
'oauth' => [
    'token_ttl' => env('OAUTH_TOKEN_TTL', 3600),       // 1 hour default
    'code_ttl' => 600,                                    // 10 minutes
    'scopes' => ['mcp:read', 'mcp:write', 'mcp:admin'],
],
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
],
```

## 8. Verification

1. **Streamable HTTP:** `POST /api/v1/mcp` with `initialize` → returns JSON-RPC response with `Mcp-Session-Id`
2. **401 flow:** `POST /api/v1/mcp` without token → `401` with `WWW-Authenticate` header
3. **Discovery:** `GET /.well-known/oauth-protected-resource` → valid JSON metadata
4. **Discovery:** `GET /.well-known/oauth-authorization-server` → valid JSON metadata
5. **OAuth flow:** authorize → code → token → authenticated MCP call
6. **PKCE:** Token exchange without valid `code_verifier` → rejected
7. **Scope enforcement:** `mcp:read` token calling `delete_prompt` → rejected
8. **API key backward compat:** Existing Bearer `urge_...` token still works on MCP endpoint
9. **GitHub OAuth:** GitHub login → URGE user created/matched → token issued
10. **Session:** Subsequent requests with `Mcp-Session-Id` maintain state
11. **stdio:** `php artisan urge:mcp-server` still works unchanged
12. **PHP tests:** All existing tests pass + new OAuth/transport tests
