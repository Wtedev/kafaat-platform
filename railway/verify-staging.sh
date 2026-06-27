#!/usr/bin/env bash
# Post-deploy verification for Railway staging (safe output, no secrets).
set -euo pipefail

if [[ "${RAILWAY_ENVIRONMENT_NAME:-}" != "staging" && "${APP_ENV:-}" != "staging" ]]; then
  echo "Refusing: this script is for staging only." >&2
  exit 1
fi

echo "== Staging verification =="
echo "APP_ENV=${APP_ENV:-unset}"
echo "APP_URL=${APP_URL:-unset}"

php artisan about --only=environment,cache,queue,database 2>/dev/null || php artisan about
php artisan migrate:status --no-ansi | tail -5
php artisan system:health
php artisan privacy:retention-status
php artisan queue:failed --no-ansi | head -20

echo "== Done =="
