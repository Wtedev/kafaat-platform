#!/usr/bin/env bash
# Runs once per Web deploy (Railway preDeployCommand). Not for Worker/Scheduler.
set -euo pipefail

if [[ "${APP_ENV:-}" == "production" && "${RAILWAY_ENVIRONMENT_NAME:-}" == "staging" ]]; then
  echo "Refusing predeploy: APP_ENV=production inside staging environment." >&2
  exit 1
fi

php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan permission:cache-reset
