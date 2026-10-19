# syntax=docker/dockerfile:1

# Pin official multi-platform images; update these digests deliberately for security patches.
FROM php:8.3-apache-bookworm@sha256:b79a53c3fb1dd4b9f350a8d99d84951512b6c21044e61bbecfee8af030b3722e AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libgmp10 libgmp-dev \
        libjpeg62-turbo libjpeg62-turbo-dev \
        libpng16-16 libpng-dev \
        libwebp7 libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd gmp opcache pdo_mysql \
    && apt-get purge -y --auto-remove libgmp-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer-binary

FROM php-base AS dependencies
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
WORKDIR /build
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_CACHE_DIR=/tmp/composer-cache
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock ./
# Application changes must not invalidate the dependency download layer.
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --no-autoloader
COPY App/ ./App/
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts --no-plugins \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim@sha256:a5f4366e834695d5f363b920c104953a535d3a9864d1bdf74566a0ebe01daee5 AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY public/assets/ ./public/assets/
RUN npm run build

FROM php-base AS web
WORKDIR /var/www/html
ENV APP_ENV=production APP_DEBUG=false APP_TIMEZONE=UTC PORT=8080

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-mytodo.ini
COPY docker/health.php /usr/local/share/mytodo/health.php
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod headers rewrite \
    && printf 'Listen ${PORT}\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/mytodo.conf \
    && a2enconf mytodo \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && mkdir -p /var/run/apache2 /var/lock/apache2 /var/www/html/storage/logs /var/www/html/public/storage/avatars \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/www/html/storage /var/www/html/public/storage

# Copy only runtime files; never ship local uploads, keys, tests, or development tooling.
COPY --from=dependencies /build/vendor/ ./vendor/
COPY --from=dependencies /build/App/ ./App/
COPY composer.json composer.lock ./
COPY config/ ./config/
COPY process/ ./process/
COPY resources/ ./resources/
COPY routes/ ./routes/
COPY views/ ./views/
COPY public/ ./public/
COPY --from=assets /build/public/build/ ./public/build/

USER www-data:www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl --fail --silent --show-error --max-time 3 "http://127.0.0.1:${PORT}/healthz" || exit 1
CMD ["apache2-foreground"]
