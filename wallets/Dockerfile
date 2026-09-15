# syntax=docker/dockerfile:1

ARG APP_URL=https://nvnhan0810.com
ARG APP_PATH_PREFIX=wallets

# -----------------------------------------------------------------------------
# PHP dependencies
# -----------------------------------------------------------------------------
FROM php:8.4-cli-bookworm AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip gd \
    && rm -rf /var/lib/apt/lists/*

ARG APP_URL
ARG APP_PATH_PREFIX=wallets
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    APP_URL=${APP_URL} \
    APP_PATH_PREFIX=${APP_PATH_PREFIX}

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# -----------------------------------------------------------------------------
# Frontend assets (Vite + Vue 3 / Inertia)
# -----------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets

WORKDIR /app

# Install JS deps first (layer cache). Include devDependencies for Vite/Vue build.
COPY package.json package-lock.json ./
RUN npm ci

# App source (incl. vendor/tightenco/ziggy imported by resources/js/app.js).
# Copy without clobbering node_modules from npm ci above.
COPY --from=vendor /app /tmp/app
RUN rm -rf /tmp/app/node_modules \
    && cp -a /tmp/app/. ./ \
    && rm -rf /tmp/app

ARG APP_PATH_PREFIX=wallets
ENV NODE_ENV=production \
    APP_PATH_PREFIX=${APP_PATH_PREFIX}
RUN npm run build \
    && test -f public/build/manifest.json

# -----------------------------------------------------------------------------
# Production runtime (nginx + php-fpm + queue + schedule)
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS runtime

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=UTC

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        ca-certificates \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo_pgsql \
        pdo_sqlite \
        zip \
        bcmath \
        opcache \
        mbstring \
        xml \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-timeouts.conf
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/default.bak

COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY --from=vendor /app/app ./app
COPY --from=vendor /app/bootstrap ./bootstrap
COPY --from=vendor /app/config ./config
COPY --from=vendor /app/database ./database
COPY --from=vendor /app/routes ./routes
COPY --from=vendor /app/resources ./resources
COPY --from=vendor /app/src ./src
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/artisan ./artisan
COPY --from=vendor /app/composer.json ./composer.json
COPY --from=vendor /app/composer.lock ./composer.lock

# Base public/ first, then Vite build so manifest + hashed assets win.
COPY public ./public
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
