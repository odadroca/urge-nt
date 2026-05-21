#!/bin/bash
# Hostinger Shared Hosting Deployment Script
# Run from the server: cd ~/domains/urge.acordado.org/app && bash deploy/hostinger.sh
#
# Structure:
#   ~/domains/urge.acordado.org/
#   ├── app/              ← Laravel app (this repo)
#   └── public_html/      ← Document root (copies of public/ with adjusted paths)
#
# PB-5 / INFRA-08: pre-deploy DB backup, build step, post-deploy health
# check, and automatic rollback (code + DB) if the health check fails.

set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN_DIR="$(dirname "$APP_DIR")"
PUBLIC_HTML="$DOMAIN_DIR/public_html"
BACKUP_DIR="$APP_DIR/storage/deploy-backups"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
HEALTH_URL="${HEALTH_URL:-${APP_URL:-http://localhost}/up}"

mkdir -p "$BACKUP_DIR"

log() { echo "==> $*"; }

# Capture current commit for rollback
cd "$APP_DIR"
PREV_COMMIT="$(git rev-parse HEAD)"
DB_PATH="$APP_DIR/database/database.sqlite"
DB_BACKUP="$BACKUP_DIR/database-$TIMESTAMP.sqlite"

rollback() {
    log "DEPLOY FAILED — rolling back to $PREV_COMMIT"
    git reset --hard "$PREV_COMMIT" || true
    if [ -f "$DB_BACKUP" ] && [ -f "$DB_PATH" ]; then
        cp "$DB_BACKUP" "$DB_PATH" || true
        log "Database restored from $DB_BACKUP"
    fi
    composer install --no-dev --optimize-autoloader --no-interaction || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    log "Rollback complete."
    exit 1
}
trap rollback ERR

log "App directory: $APP_DIR"
log "Public HTML:   $PUBLIC_HTML"
log "Health URL:    $HEALTH_URL"

# 1. Backup the SQLite database before anything mutates it
if [ -f "$DB_PATH" ]; then
    log "Backing up database → $DB_BACKUP"
    cp "$DB_PATH" "$DB_BACKUP"
    # Keep only the 10 most recent backups
    ls -1t "$BACKUP_DIR"/database-*.sqlite 2>/dev/null | tail -n +11 | xargs -r rm -f
fi

# 2. Pull latest code
log "Pulling latest code..."
git pull origin main

# 3. PHP deps (production)
log "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Frontend build (public/build is no longer committed — INFRA-06)
if command -v npm >/dev/null 2>&1; then
    log "Building frontend assets..."
    npm ci
    npm run build
else
    log "WARNING: npm not found — assuming public/build was uploaded out-of-band."
fi

# 5. Migrations
log "Running migrations..."
php artisan migrate --force

# 6. Sync public assets to the document root
log "Syncing public assets to public_html..."
rsync -av --delete --exclude='index.php' --exclude='.htaccess' --exclude='storage' "$APP_DIR/public/" "$PUBLIC_HTML/"

# 7. Hostinger-specific front controller
log "Writing public_html/index.php..."
cat > "$PUBLIC_HTML/index.php" << 'INDEXPHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

set_time_limit(120);

if (file_exists($maintenance = __DIR__.'/../app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../app/vendor/autoload.php';

(require_once __DIR__.'/../app/bootstrap/app.php')
    ->handleRequest(Request::capture());
INDEXPHP

[ -f "$PUBLIC_HTML/.htaccess" ] || cp "$APP_DIR/public/.htaccess" "$PUBLIC_HTML/.htaccess"
[ -L "$PUBLIC_HTML/storage" ] || ln -s "$APP_DIR/storage/app/public" "$PUBLIC_HTML/storage"

# 8. Cache for production
log "Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Post-deploy health check — triggers rollback (via ERR trap) on failure
log "Health check: $HEALTH_URL"
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$HEALTH_URL" || echo '000')"
if [ "$HTTP_CODE" != "200" ]; then
    log "Health check returned HTTP $HTTP_CODE (expected 200)"
    false
fi

trap - ERR
log "Done! Site deployed to $PUBLIC_HTML (health OK, prev=$PREV_COMMIT)"
