#!/usr/bin/env bash
# Prepares the Laravel app before starting the main process (php-fpm or a worker).
# Only the "app" service runs migrations (RUN_MIGRATIONS=true) to avoid races.
set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
    echo "[entrypoint] creating .env from .env.example"
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

echo "[entrypoint] waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}"
for _ in $(seq 1 60); do
    if mysqladmin ping -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" --silent 2>/dev/null; then
        break
    fi
    sleep 2
done

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan storage:link >/dev/null 2>&1 || true

    # Seed demo data once (development only): only when there are no users yet.
    if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
        users=$(mysql -N -s -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e 'SELECT COUNT(*) FROM users' 2>/dev/null || echo 0)
        if [ "${users}" = "0" ]; then
            php artisan db:seed --force
        fi
    fi
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
