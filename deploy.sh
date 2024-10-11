#!/bin/bash
set -e

echo "Deployment started ..."

# Enter maintenance mode or return true
# if already is in maintenance mode
(php artisan down) || true

# Pull the latest version of the app
git pull origin main

# Install composer dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader

# Restart the queue job process
sudo systemctl restart necessi-queue-process.service

# Clear the old cache
php artisan clear-compiled

# Clear config file
php artisan config:clear

# Recreate cache
php artisan optimize

# Create link in case not present
php artisan storage:link

# Exit maintenance mode
php artisan up

echo "Deployment finished!"
