# OAuth 2.1 Refresh Token Support

## Context

URGE's OAuth access tokens expire after 1 hour with no refresh mechanism. This forces Claude Desktop / Claude.ai users to re-authenticate every hour, breaking MCP sessions. Services like Supabase avoid this by issuing refresh tokens that silently renew access tokens in the background.

## Approach

Add RFC-compliant refresh token support with rotation (OAuth 2.1 best practice): each refresh issues a new access token + new refresh token, invalidating the old refresh token.

## Implementation

### Task 1: Config + env

**Modify:** `config/urge.php` — add `refresh_token_ttl` (default 30 days)
**Modify:** `.env.example` — add `OAUTH_REFRESH_TOKEN_TTL=2592000`

### Task 2: Migration + Model

**Create:** `database/migrations/2026_04_23_000001_create_oauth_refresh_tokens_table.php`
- `token` (string 128, unique, SHA256 hash)
- `user_id` (FK → users, cascade delete)
- `client_id` (string 2048)
- `scope` (string, default 'mcp:read')
- `access_token_id` (FK → oauth_access_tokens, cascade delete)
- `expires_at` (datetime)
- timestamps

**Create:** `app/Models/OAuthRefreshToken.php` — same pattern as `OAuthAccessToken` with `isExpired()`, `user()`, `accessToken()` relations

### Task 3: OAuthService changes

**Modify:** `app/Services/OAuthService.php`

1. In `exchangeCode()`: after creating the access token, also create a refresh token. Attach `raw_refresh_token` as a transient property on the returned token.

2. Add `refreshToken(string $rawRefreshToken, string $clientId, ?string $scope)` method:
   - Look up refresh token by SHA256 hash
   - Validate: not expired, client_id matches, scope is same or subset
   - Create new access token + new refresh token (rotation)
   - Delete old refresh token + old access token
   - Return new token with both `raw_token` and `raw_refresh_token`

### Task 4: OAuthController changes

**Modify:** `app/Http/Controllers/OAuthController.php`

1. Split `token()` into a dispatcher using `match($grantType)`:
   - `authorization_code` → `handleAuthorizationCodeGrant()` (existing logic, extracted)
   - `refresh_token` → `handleRefreshTokenGrant()` (new)
   - default → `unsupported_grant_type` error

2. Both handlers return `refresh_token` in the JSON response alongside `access_token`

3. `handleRefreshTokenGrant()` requires: `refresh_token`, `client_id`. Optional: `scope` (for downscoping).

### Task 5: Discovery endpoint

**Modify:** `app/Http/Controllers/WellKnownController.php`
- Add `refresh_token` to `grant_types_supported`

### Task 6: Tests

**Modify:** `tests/Feature/OAuthFlowTest.php`

New tests:
- Token response includes `refresh_token` field
- Refresh token grants new access token (full flow)
- Rotation invalidates old refresh token (single-use)
- Refresh token bound to client_id (cross-client rejected)
- Scope downscoping works
- Scope escalation rejected
- Expired refresh token rejected
- Missing parameters returns error
- Well-known advertises `refresh_token` grant

Update existing tests:
- `test_full_oauth_flow_with_pkce` — assert `refresh_token` in response
- `test_full_oauth_flow_with_dynamic_registration` — same

### Task 7: Run full test suite + verify

- `php artisan test` — 376+ tests pass
- Manual: complete OAuth flow in browser, verify refresh_token in response
- Deploy to Hostinger, reconnect Claude Desktop, verify session persists beyond 1 hour

## Security

- SHA256 hashed storage (same as access tokens)
- Single-use rotation (old refresh token deleted on use)
- Client-bound (refresh token only usable by issuing client)
- Scope downscoping only (no escalation)
- No impact on API key auth flow (OpenAI/ChatGPT unchanged)

## Backward Compatibility

- No changes to `oauth_access_tokens` table
- Existing tokens keep working until expiry
- `refresh_token` field in response is additive (clients that don't use it ignore it)
