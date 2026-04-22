# URGE v2 Installation Guide

## Requirements

- PHP 8.3+
- Composer 2.x
- Node.js 18+ and npm
- SQLite 3

## Quick Start

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Database
touch database/database.sqlite
php artisan migrate

# 4. (Optional) Seed evaluation prompt, pipeline, and settings
php artisan urge:seed-evaluation

# 5. Build frontend assets
npm run build

# 6. Start development server
php artisan serve
# Visit http://127.0.0.1:8000
```

## First Run

1. Register at `/register` — the first user automatically becomes **admin**
2. You'll land on Browse — click **+ New Prompt** to create your first prompt
3. The Workspace opens: write content in the editor, click **Save Version**
4. Paste LLM responses via **+ Paste Result** in the results panel
5. Star and rate results for quick access from Browse
6. Configure evaluation under **Settings > Evaluation** — enable auto-evaluate, choose a provider, adjust dimension weights
7. Your prompts are private by default — create teams under **Teams** to share with others

## Development

```bash
# Run dev server with Vite HMR
php artisan serve &
npm run dev

# Or if composer dev script is configured:
composer dev

# Run tests
php artisan test
```

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `URGE_MAX_INCLUDE_DEPTH` | 10 | Max recursion depth for `{{>slug}}` includes |
| `CURL_SSL_VERIFY` | true | SSL verification for LLM API calls |
| `APP_KEY` | (generated) | Must match v1's key if migrating encrypted LLM provider keys |
| `OAUTH_TOKEN_TTL` | 3600 | OAuth access token lifetime in seconds |
| `GITHUB_CLIENT_ID` | — | GitHub OAuth App client ID |
| `GITHUB_CLIENT_SECRET` | — | GitHub OAuth App client secret |

### GitHub OAuth Setup

To enable GitHub as an external identity provider:

1. Create a GitHub OAuth App at https://github.com/settings/developers
2. Set the callback URL to `https://your-urge-instance.com/oauth/github/callback`
3. Copy the Client ID and Client Secret into your `.env`:
   ```
   GITHUB_CLIENT_ID=your_client_id
   GITHUB_CLIENT_SECRET=your_client_secret
   ```

### OAuth Client Management

Create pre-registered OAuth clients for third-party MCP consumers:

```bash
# Public client (PKCE only — for clients that support Dynamic Client Registration)
php artisan oauth:create-client "My Client" --redirect="https://example.com/callback"

# Confidential client (client_secret — for Mistral Le Chat and similar)
php artisan oauth:create-client "Le Chat" \
  --redirect="https://callback.mistral.ai/v1/integrations_auth/oauth2_callback" \
  --confidential
```

The command outputs the `client_id` and (for confidential clients) the `client_secret`. Store these securely — the secret is only shown once.

### Mistral Le Chat Setup

To connect Mistral Le Chat to URGE as an MCP integration:

1. Create a confidential OAuth client:
   ```bash
   php artisan oauth:create-client "Le Chat" \
     --redirect="https://callback.mistral.ai/v1/integrations_auth/oauth2_callback" \
     --confidential
   ```
2. Note the `client_id` and `client_secret` from the output
3. In Le Chat, add URGE as an MCP integration:
   - MCP server URL: `https://your-urge-instance.com/api/v1/mcp`
   - Client ID and Client Secret from step 2
4. Le Chat discovers OAuth endpoints via `/.well-known/openid-configuration`
5. On first use, you'll be redirected to URGE to authorize access

## Production Deployment (Shared Hosting)

1. Upload all files to your hosting directory
2. Set document root to the `public/` directory
3. Run `composer install --no-dev --optimize-autoloader`
4. Run `npm run build` locally and upload `public/build/`
5. Set `APP_ENV=production`, `APP_DEBUG=false` in `.env`
6. Run `php artisan migrate --force`
7. Run `php artisan urge:seed-evaluation` (creates default evaluation prompt, pipeline, and settings)
8. Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`

No Node.js required on the server — `npm run build` produces static assets for the React SPA.
