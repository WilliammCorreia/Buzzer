FROM php:8.3-fpm-bookworm

# --- System packages & PHP extensions -------------------------------------
# mlocati/docker-php-extension-installer makes installing native extensions
# (intl, pdo_pgsql, zip, opcache...) and Composer reliable & cache-friendly.
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

# Remap the php-fpm runtime user to the host UID/GID so that files created
# inside the container (cache, logs, composer output, migrations...) stay
# owned by the developer on the host bind mount.
ARG UID=1000
ARG GID=1000
RUN if [ "$GID" != "33" ]; then groupmod -g "$GID" www-data; fi \
    && if [ "$UID" != "33" ]; then usermod -u "$UID" www-data; fi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

# Composer caches under HOME; give www-data a writable home.
ENV COMPOSER_HOME=/tmp/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["php-fpm"]
