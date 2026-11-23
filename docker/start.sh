#!/bin/sh
# Container entrypoint: prepares the app and serves it on $PORT.
set -e
cd /app

[ -f .env ] || cp .env.example .env

# Public address used for links and uploaded media.
# Render sets RENDER_EXTERNAL_URL; Hugging Face Spaces set SPACE_HOST (e.g. name-fgla-erp.hf.space).
if [ -z "$APP_URL" ] && [ -n "$SPACE_HOST" ]; then APP_URL="https://${SPACE_HOST}"; fi
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"

if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction
fi

if [ "${DB_CONNECTION}" = "sqlite" ]; then
  touch database/database.sqlite
fi

if [ "${DEMO_RESET}" = "true" ]; then
  echo "Building the demo college (takes about 30 seconds)…"
  php artisan migrate:fresh --seed --force --no-interaction
else
  php artisan migrate --force --no-interaction
fi

php artisan optimize:clear >/dev/null 2>&1 || true

# Scheduler in the background: class reminders for teachers (classes:remind, every minute).
php artisan schedule:work --no-interaction >/dev/null 2>&1 &

echo "Serving on port ${PORT} — ${APP_URL}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}" --no-reload
