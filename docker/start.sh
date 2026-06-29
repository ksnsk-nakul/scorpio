#!/bin/sh
set -e

cd /var/www/html

# Generate app key if not set
php artisan key:generate --no-interaction --force 2>/dev/null || true

# Run migrations
php artisan migrate --force --no-interaction

# Seed if first run
php artisan db:seed --class=RoleSeeder --force --no-interaction 2>/dev/null || true
php artisan db:seed --class=SettingSeeder --force --no-interaction 2>/dev/null || true
php artisan db:seed --class=UserSeeder --force --no-interaction 2>/dev/null || true

# Link storage
php artisan storage:link --force 2>/dev/null || true

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in foreground
nginx -g "daemon off;"
