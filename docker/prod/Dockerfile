# syntax=docker/dockerfile:1
#
# Production image. Unlike the development stack, the source code is baked into
# the image (no bind mount): OPcache can therefore trust its bytecode, and the
# container filesystem carries no .git, no tests and no dev dependencies.
#
# Two targets are built from the same builder stage so the compiled assets in
# public/ are byte-for-byte the ones PHP serves:
#   --target php    -> php-fpm running the application
#   --target caddy  -> Caddy with the static files and automatic HTTPS

########################  common base  ########################
FROM php:8.3-fpm-bookworm AS php_base

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        intl \
        pdo_pgsql \
        zip \
        opcache \
        @composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq5 \
    && rm -rf /var/lib/apt/lists/*

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    APP_ENV=prod \
    APP_DEBUG=0

########################  builder  ########################
FROM php_base AS builder

# Dependencies first: this layer survives every change that leaves
# composer.json / composer.lock untouched.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .

# Authoritative classmap, warmed container, minified Tailwind build and the
# digested asset files consumed by importmap()/asset().
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && composer run-script post-install-cmd --no-interaction \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && rm -rf /tmp/composer var/log/*

########################  php-fpm runtime  ########################
FROM php_base AS php

COPY --from=builder /app /app

RUN mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var

EXPOSE 9000
CMD ["php-fpm"]

########################  caddy runtime  ########################
FROM caddy:2-alpine AS caddy

COPY docker/prod/Caddyfile /etc/caddy/Caddyfile

# Caddy serves the static files and resolves try_files against this root, so it
# needs public/ at the very same path php-fpm knows.
COPY --from=builder /app/public /app/public
