#!/usr/bin/env bash
# Web service entrypoint — HTTP only; no queue worker or scheduler here.
set -euo pipefail

php artisan optimize:clear
php artisan storage:link 2>/dev/null || true
php artisan optimize
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
