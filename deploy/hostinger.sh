#!/bin/bash
# Hostinger Shared Hosting Deployment Script
# Run from the server: cd ~/domains/urge.acordado.org/app && bash deploy/hostinger.sh
#
# Structure:
#   ~/domains/urge.acordado.org/
#   ├── app/              ← Laravel app (this repo)
#   └── public_html/      ← Document root (copies of public/ with adjusted paths)

set -e

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN_DIR="$(dirname "$APP_DIR")"
PUBLIC_HTML="$DOMAIN_DIR/public_html"

echo "==> App directory: $APP_DIR"
echo "==> Public HTML:   $PUBLIC_HTML"

# Pull latest code
echo "==> Pulling latest code..."
cd "$APP_DIR"
git pull origin main

# Install composer dependencies (production)
echo "==> Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force

# Copy public assets to public_html
echo "==> Syncing public assets to public_html..."
rsync -av --delete --exclude='index.php' --exclude='.htaccess' --exclude='storage' "$APP_DIR/public/" "$PUBLIC_HTML/"

# Create the Hostinger-specific index.php (paths point to ../app/)
echo "==> Writing public_html/index.php..."
cat > "$PUBLIC_HTML/index.php" << 'INDEXPHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

set_time_limit(120);

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../app/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../app/bootstrap/app.php')
    ->handleRequest(Request::capture());
INDEXPHP

# Copy .htaccess if not present
if [ ! -f "$PUBLIC_HTML/.htaccess" ]; then
    cp "$APP_DIR/public/.htaccess" "$PUBLIC_HTML/.htaccess"
fi

# Ensure storage link exists in public_html
if [ ! -L "$PUBLIC_HTML/storage" ]; then
    ln -s "$APP_DIR/storage/app/public" "$PUBLIC_HTML/storage"
fi

# Cache for production
echo "==> Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Done! Site deployed to $PUBLIC_HTML"
