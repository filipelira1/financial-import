#!/bin/sh
# The "app" container prepares the project once (dependencies, key, schema, demo user)
# and then marks itself ready. Workers wait for that through the compose healthcheck.
set -e

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    rm -f /tmp/ready

    [ -f .env ] || cp .env.example .env

    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist --no-progress
    fi

    grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

    php artisan migrate --force
    php artisan db:seed --force

    touch /tmp/ready
fi

exec "$@"
