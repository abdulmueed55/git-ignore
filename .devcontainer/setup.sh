#!/usr/bin/env bash
# Runs once when the Codespace is created: installs everything and builds the demo college.
set -euo pipefail
cd "$(dirname "$0")/.."
export XDEBUG_MODE=off DEBIAN_FRONTEND=noninteractive

echo "==> PHP extensions (gd, intl, zip)"
if ! php -m | grep -qi '^gd$' || ! php -m | grep -qi '^intl$' || ! php -m | grep -qi '^zip$'; then
  # The base image ships a Yarn apt source whose signing key has expired; we don't use Yarn.
  sudo rm -f /etc/apt/sources.list.d/yarn.list /etc/apt/sources.list.d/yarn*.list
  sudo apt-get update -qq || echo "(apt update reported warnings — continuing)"
  sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libzip-dev >/dev/null
  # sudo drops PHP_INI_DIR, which docker-php-ext-enable needs to write the .ini files.
  INI="${PHP_INI_DIR:-/usr/local/etc/php}"
  sudo env PHP_INI_DIR="$INI" DEBIAN_FRONTEND=noninteractive docker-php-ext-configure gd --with-freetype --with-jpeg >/dev/null
  sudo env PHP_INI_DIR="$INI" DEBIAN_FRONTEND=noninteractive docker-php-ext-install -j"$(nproc)" gd intl zip bcmath exif >/dev/null
  php -m | grep -qi '^gd$' || { echo "gd extension did not install"; exit 1; }
fi

echo "==> PHP packages"
composer install --no-interaction --prefer-dist --no-progress

echo "==> Front-end"
npm ci --no-audit --no-fund
npm run build

echo "==> Configuration"
[ -f .env ] || cp .env.example .env
php artisan key:generate --force --no-interaction
touch database/database.sqlite

echo "==> Demo college (staff, students, timetable, attendance, admissions, website)"
APP_ENV=production APP_DEMO=true php artisan migrate:fresh --seed --force --no-interaction
php artisan storage:link --force >/dev/null 2>&1 || true
echo "==> Setup done"
