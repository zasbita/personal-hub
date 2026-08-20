#!/bin/bash
set -e
cd /var/www/personal-hub
git config --global --add safe.directory /var/www/personal-hub
git fetch origin main
git reset --hard origin/main
composer install --no-dev --no-interaction --prefer-dist
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
chown -R www-data:www-data storage bootstrap/cache public/build
echo DEPLOY_OK