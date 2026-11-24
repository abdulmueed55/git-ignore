#!/usr/bin/env bash
# Runs every time the Codespace starts: serves the ERP on port 8000 and makes the link public.
cd "$(dirname "$0")/.."

# The public https address GitHub gives port 8000.
if [ -n "${CODESPACE_NAME:-}" ]; then
  export APP_URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
fi
export APP_ENV=production APP_DEBUG=false APP_DEMO=true TRUST_PROXIES=true DB_CONNECTION=sqlite \
       LOG_CHANNEL=stack QUEUE_CONNECTION=sync PHP_CLI_SERVER_WORKERS=4
php artisan optimize:clear >/dev/null 2>&1 || true

pkill -f "artisan serve" >/dev/null 2>&1 || true
pkill -f "schedule:work" >/dev/null 2>&1 || true
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
nohup php artisan schedule:work > storage/logs/schedule.log 2>&1 &

# Make the link open for everyone (phones, the Android app) — not only for GitHub-signed-in users.
if [ -n "${CODESPACE_NAME:-}" ]; then
  sleep 3
  gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" >/dev/null 2>&1 \
    && echo "Port 8000 is public." \
    || echo "Make the link public: PORTS tab → right-click port 8000 → Port Visibility → Public."
  echo ""
  echo "=============================================================="
  echo " College ERP live at:  ${APP_URL}"
  echo " Student portal:       ${APP_URL}/student"
  echo " Logins (password 'password'): principal@ teacher@ admissions@"
  echo "   editor@ admin@ fglacollege.edu.pk  ·  student FSC-PM-26-001"
  echo "=============================================================="
  echo "${APP_URL}" > storage/app/live-url.txt
fi
