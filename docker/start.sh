#!/bin/sh
# Container entrypoint: prepares the app and serves it on $PORT.
set -e
cd /app

[ -f .env ] || cp .env.example .env

# Public address used for links and uploaded media (Render sets RENDER_EXTERNAL_URL).
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

echo "Serving on port ${PORT} — ${APP_URL}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}" --no-reload
