# syntax=docker/dockerfile:1

# Railway passes this non-secret build argument; normal builds remain web-only.
ARG MYTODO_IMAGE_VARIANT=web

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

FROM php-base AS scheduler-binary
# Pin both the release and its official GitHub SHA-256 asset digests.
RUN set -eu; \
    architecture="$(dpkg --print-architecture)"; \
    case "$architecture" in \
        amd64) checksum=d2d7c97fbfefc763d5bbcbbb3f8d61f9dd3410ce5d05a737c414994b80b8cdd0 ;; \
        arm64) checksum=a1c0af9872e8de89a1ce5c1b596e26c42917d5a2cf914d98faee2e28156899e2 ;; \
        *) echo 'Unsupported scheduler architecture.' >&2; exit 1 ;; \
    esac; \
    curl --fail --silent --show-error --location --retry 3 --proto '=https' \
        "https://github.com/aptible/supercronic/releases/download/v0.2.50/supercronic-linux-${architecture}" \
        --output /usr/local/bin/supercronic; \
    printf '%s  %s\n' "$checksum" /usr/local/bin/supercronic | sha256sum --check; \
    chmod 0755 /usr/local/bin/supercronic

FROM php-base AS dependencies
COPY --from=composer-binary /usr/bin/composer /usr/local/bin/composer
WORKDIR /build
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_CACHE_DIR=/tmp/composer-cache
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock ./
# Application changes must not invalidate the dependency download layer.
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --no-autoloader
COPY App/ ./App/
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts --no-plugins \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim@sha256:a5f4366e834695d5f363b920c104953a535d3a9864d1bdf74566a0ebe01daee5 AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY public/assets/ ./public/assets/
RUN npm run build

FROM php-base AS web
WORKDIR /var/www/html
ENV APP_ENV=production APP_DEBUG=false APP_TIMEZONE=UTC PORT=8080

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-mytodo.ini
COPY docker/health.php /usr/local/share/mytodo/health.php
# mod_php requires prefork; never inherit a second active MPM.
# Freeze module selection so restored base-image symlinks cannot load another MPM.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork headers rewrite \
    && printf '%s\n' 'Include /etc/apache2/mods-available/mpm_prefork.load' > /etc/apache2/mytodo-modules.conf \
    && find /etc/apache2/mods-enabled -maxdepth 1 -name '*.load' ! -name 'mpm_*.load' -printf 'Include %p\n' \
        | sort >> /etc/apache2/mytodo-modules.conf \
    && printf '%s\n' 'Include /etc/apache2/mods-available/mpm_prefork.conf' >> /etc/apache2/mytodo-modules.conf \
    && find /etc/apache2/mods-enabled -maxdepth 1 -name '*.conf' ! -name 'mpm_*.conf' -printf 'Include %p\n' \
        | sort >> /etc/apache2/mytodo-modules.conf \
    && sed -i \
        -e 's@^IncludeOptional mods-enabled/\*.load$@Include /etc/apache2/mytodo-modules.conf@' \
        -e 's@^IncludeOptional mods-enabled/\*.conf$@# Module configuration is included by mytodo-modules.conf.@' \
        /etc/apache2/apache2.conf \
    && printf 'Listen ${PORT}\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/mytodo.conf \
    && a2enconf mytodo \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && mkdir -p /var/run/apache2 /var/lock/apache2 /var/www/html/storage/logs /var/www/html/public/storage/avatars \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/www/html/storage /var/www/html/public/storage \
    && apache2ctl -t

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

# A separate always-on service runs cron, never Apache or the web healthcheck.
FROM web AS scheduler
COPY --from=scheduler-binary /usr/local/bin/supercronic /usr/local/bin/supercronic
COPY --chmod=0755 docker/start-scheduler.sh /usr/local/bin/mytodo-scheduler
COPY --chmod=0444 docker/scheduler.crontab /etc/mytodo.crontab
ENV TZ=UTC
HEALTHCHECK NONE
CMD ["/usr/local/bin/mytodo-scheduler"]

# Keep the web service unchanged unless its build explicitly selects scheduler.
FROM ${MYTODO_IMAGE_VARIANT} AS runtime
