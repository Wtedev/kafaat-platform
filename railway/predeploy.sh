#!/usr/bin/env bash
# Web-only: migrations and permission seed (invoked from start.sh on kafaat-web-staging).
set -euo pipefail

if [[ "${RAILWAY_ENVIRONMENT_NAME:-}" == "staging" && "${APP_ENV:-}" != "staging" ]]; then
  echo "Refusing predeploy: APP_ENV must be staging (got: ${APP_ENV:-unset})." >&2
  exit 1
fi

if [[ "${APP_ENV:-}" == "production" && "${RAILWAY_ENVIRONMENT_NAME:-}" == "staging" ]]; then
  echo "Refusing predeploy: APP_ENV=production inside Railway staging environment." >&2
  exit 1
fi

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=PrivacyPolicySeeder --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan permission:cache-reset
php artisan cache:clear
