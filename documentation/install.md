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

# 4. Build frontend assets
npm run build

# 5. Start development server
php artisan serve
# Visit http://127.0.0.1:8000
```

## First Run

1. Register at `/register` — the first user automatically becomes **admin**
2. You'll land on Browse — click **+ New Prompt** to create your first prompt
3. The Workspace opens: write content in the editor, click **Save Version**
4. Paste LLM responses via **+ Paste Result** in the results panel
5. Star and rate results for quick access from Browse
6. Your prompts are private by default — create teams under **Teams** to share with others

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

## Production Deployment (Shared Hosting)

1. Upload all files to your hosting directory
2. Set document root to the `public/` directory
3. Run `composer install --no-dev --optimize-autoloader`
4. Run `npm run build` locally and upload `public/build/`
5. Set `APP_ENV=production`, `APP_DEBUG=false` in `.env`
6. Run `php artisan migrate --force`
7. Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`

No Node.js required on the server — Livewire components are server-rendered PHP.
