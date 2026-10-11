# syntax=docker/dockerfile:1

# ---------- Stage 1: PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---------- Stage 2: build asset (Vite butuh artisan untuk wayfinder) ----------
FROM php:8.3-cli-alpine AS assets
RUN apk add --no-cache libstdc++ libgcc \
    && docker-php-ext-install pcntl
COPY --from=oven/bun:1-alpine /usr/local/bin/bun /usr/local/bin/bun
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY . .
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && DB_CONNECTION=sqlite bun run build \
    && rm -f .env

# ---------- Stage 3: runtime ----------
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache postgresql-libs icu-libs libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath pcntl opcache mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------- Stage 4: nginx (bawa file public dari image app) ----------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
