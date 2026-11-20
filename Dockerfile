# Demo / small-deployment image for the FG Liaquat Ali Degree College ERP.
# Builds the front-end assets, installs PHP dependencies and, on start, creates a
# fresh SQLite demo college (see docker/start.sh). For a real deployment set
# DEMO_RESET=false and point DB_* at MySQL.

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources resources
RUN npm run build

FROM php:8.3-cli-bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd intl zip bcmath exif pcntl \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress
COPY . .
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --optimize --no-interaction \
 && chmod +x docker/start.sh \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_DEMO=true \
    TRUST_PROXIES=true \
    DB_CONNECTION=sqlite \
    LOG_CHANNEL=stderr \
    QUEUE_CONNECTION=sync \
    DEMO_RESET=true \
    PHP_CLI_SERVER_WORKERS=4 \
    PORT=8080

EXPOSE 8080
CMD ["docker/start.sh"]
