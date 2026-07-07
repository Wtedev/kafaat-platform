#!/usr/bin/env bash
# Web-only: migrations, permissions, and governance content (Railway preDeploy + staging web boot).
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
php artisan db:seed --class=GovernanceContentSeeder --force
php artisan db:seed --class=RegulationsSeeder --force
php artisan db:seed --class=PartnerSeeder --force
php artisan permission:cache-reset
php artisan cache:clear
