#!/usr/bin/env sh
set -e

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

php artisan key:generate --force

php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider" --force || true

php artisan migrate --force

php artisan serve --host=0.0.0.0 --port=8000
