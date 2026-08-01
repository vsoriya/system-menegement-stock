#!/usr/bin/env bash
set -e

cd /var/www/html

php artisan storage:link 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force

# Render sets $PORT; default 10000 for local docker runs
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
