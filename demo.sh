#!/usr/bin/env bash
# FG Liaquat Ali Degree College ERP - one-command local demo (macOS / Linux)
set -euo pipefail
cd "$(dirname "$0")"
composer install --no-interaction
composer run demo
echo
echo "Demo ready. Open http://localhost:8000  (password for all demo accounts: password)"
php artisan serve
