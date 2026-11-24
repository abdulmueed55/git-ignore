# FG Liaquat Ali Degree College ERP — live demo on Hugging Face Spaces.
# Note (Nov 2026): Hugging Face now requires a PRO subscription for Docker Spaces; for a free
# demo use GitHub Codespaces instead (.devcontainer/, see docs/LIVE-DEMO.md).
#
# How to use: create a Space (SDK: Docker, hardware: CPU basic · free), then replace the
# Space's Dockerfile with this file. The Space builds the app straight from GitHub.
# The demo college is rebuilt on every start; demo logins use the password "password".

FROM node:22-bookworm-slim AS source
RUN apt-get update && apt-get install -y --no-install-recommends git ca-certificates && rm -rf /var/lib/apt/lists/*
ARG REPO=https://github.com/abdulmueed55/git-ignore.git
ARG BRANCH=claude/eloquent-curie-taascf
RUN git clone --depth 1 --branch "$BRANCH" "$REPO" /app
WORKDIR /app
RUN npm ci --no-audit --no-fund && npm run build && rm -rf node_modules

FROM php:8.3-cli-bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd intl zip bcmath exif pcntl \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY --from=source /app /app
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress \
 && composer dump-autoload --optimize --no-dev --no-interaction \
 && chmod +x docker/start.sh \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private \
 && ln -sfn /app/storage/app/public public/storage \
 && cp -n .env.example .env \
 && touch database/database.sqlite \
 && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nupload_max_filesize=20M\npost_max_size=100M\nmax_file_uploads=30\n' > /usr/local/etc/php/conf.d/zz-hardening.ini \
 && useradd -m -u 1000 app \
 && chown -R app:app storage bootstrap/cache database .env

# Hugging Face runs Spaces as user 1000 and sends traffic to port 7860.
USER 1000
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_DEMO=true \
    TRUST_PROXIES=true \
    DB_CONNECTION=sqlite \
    LOG_CHANNEL=stderr \
    QUEUE_CONNECTION=sync \
    DEMO_RESET=true \
    PHP_CLI_SERVER_WORKERS=4 \
    PORT=7860

EXPOSE 7860
CMD ["docker/start.sh"]
