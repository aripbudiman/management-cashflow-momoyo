#!/bin/sh
set -e

cd /var/www/html

# Hanya container "app" (php-fpm) yang menjalankan migrasi & cache
if [ "$1" = "php-fpm" ]; then
    echo "Menunggu database..."
    until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
        sleep 2
    done

    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
    php artisan optimize
fi

exec "$@"
