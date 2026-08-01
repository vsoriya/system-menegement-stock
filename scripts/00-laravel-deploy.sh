#!/usr/bin/env bash
set -e

echo "Running composer..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Linking public storage..."
php artisan storage:link || true

# Creates admin if missing; never overwrites an existing password.
# Look in the Render deploy logs for the printed password.
echo "Seeding admin user..."
php artisan db:seed --class=UserSeeder --force

echo "Deploy script finished."