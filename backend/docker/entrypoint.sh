#!/usr/bin/env sh
set -e

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

set_env_value() {
  key="$1"
  value="$2"

  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf "\n%s=%s\n" "$key" "$value" >> .env
  fi
}

if [ -n "${DB_CONNECTION:-}" ]; then set_env_value DB_CONNECTION "$DB_CONNECTION"; fi
if [ -n "${DB_HOST:-}" ]; then set_env_value DB_HOST "$DB_HOST"; fi
if [ -n "${DB_PORT:-}" ]; then set_env_value DB_PORT "$DB_PORT"; fi
if [ -n "${DB_DATABASE:-}" ]; then set_env_value DB_DATABASE "$DB_DATABASE"; fi
if [ -n "${DB_USERNAME:-}" ]; then set_env_value DB_USERNAME "$DB_USERNAME"; fi
if [ -n "${DB_PASSWORD:-}" ]; then set_env_value DB_PASSWORD "$DB_PASSWORD"; fi
if [ -n "${FRONTEND_URL:-}" ]; then set_env_value FRONTEND_URL "$FRONTEND_URL"; fi
if [ -n "${SANCTUM_STATEFUL_DOMAINS:-}" ]; then set_env_value SANCTUM_STATEFUL_DOMAINS "$SANCTUM_STATEFUL_DOMAINS"; fi
if [ -n "${SESSION_DOMAIN:-}" ]; then set_env_value SESSION_DOMAIN "$SESSION_DOMAIN"; fi

php artisan key:generate --force

php artisan config:clear || true

php artisan migrate --force

php artisan serve --host=0.0.0.0 --port=8000
