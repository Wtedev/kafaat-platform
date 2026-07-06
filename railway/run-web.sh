#!/usr/bin/env bash
# Web service entrypoint — HTTP only; no queue worker or scheduler here.
set -euo pipefail

bash railway/predeploy.sh

if [[ -d database/seeders/assets ]]; then
  mkdir -p public/governance
  cp -rn database/seeders/assets/. public/governance/ 2>/dev/null || true
fi

php artisan optimize:clear
php artisan storage:link 2>/dev/null || true
php artisan optimize
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
