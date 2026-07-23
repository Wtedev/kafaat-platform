#!/usr/bin/env bash
# Post-deploy smoke checks for Production (safe output, no secrets printed).
# Run against a machine/shell that has production env vars, or via Railway shell
# on the web service: railway run -s kafaat-platform --environment production bash railway/verify-production.sh
set -euo pipefail

if [[ "${APP_ENV:-}" != "production" ]]; then
  echo "Refusing: APP_ENV must be production (got: ${APP_ENV:-unset})." >&2
  exit 1
fi

echo "== Production verification =="
echo "APP_URL=${APP_URL:-unset}"
echo "QUEUE_CONNECTION=${QUEUE_CONNECTION:-unset}"
echo "MAIL_MAILER=${MAIL_MAILER:-unset}"
echo "PUBLIC_DISK_DRIVER=${PUBLIC_DISK_DRIVER:-unset}"
echo "PRIVATE_DOCUMENTS_DISK=${PRIVATE_DOCUMENTS_DISK:-unset}"
echo "PUBLIC_STORAGE_PERSISTENT=${PUBLIC_STORAGE_PERSISTENT:-unset}"

if [[ "${MAIL_MAILER:-}" == "log" || "${MAIL_MAILER:-}" == "array" ]]; then
  echo "FAIL: MAIL_MAILER=${MAIL_MAILER} is not allowed in production." >&2
  exit 1
fi

php artisan about --only=environment,cache,queue,database 2>/dev/null || php artisan about
php artisan migrate:status --no-ansi | tail -8
php artisan system:health
php artisan queue:failed --no-ansi | head -20

echo "== HTTP health (optional; needs curl + APP_URL) =="
if command -v curl >/dev/null 2>&1 && [[ -n "${APP_URL:-}" ]]; then
  code="$(curl -sS -o /dev/null -w '%{http_code}' "${APP_URL%/}/up" || true)"
  echo "GET ${APP_URL%/}/up -> HTTP ${code}"
  if [[ "$code" != "200" ]]; then
    echo "FAIL: /up did not return 200" >&2
    exit 1
  fi
fi

echo "== Done =="
