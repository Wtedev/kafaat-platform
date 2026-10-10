#!/usr/bin/env bash
# Web service entrypoint — HTTP only; no queue worker or scheduler here.
# Migrations and seeders run once in Railway preDeploy (railway/predeploy.sh).
# Do not call that script again here: this process is the one /up times.
set -euo pipefail

if [[ -d public/governance/surveys ]]; then
  rm -rf public/governance
fi

if [[ -d database/seeders/assets ]]; then
  mkdir -p public/governance-docs
  cp -rn database/seeders/assets/. public/governance-docs/ 2>/dev/null || true
fi

if [[ -d database/seeders/assets/regulations ]]; then
  mkdir -p public/regulation-docs/files
  cp -rn database/seeders/assets/regulations/. public/regulation-docs/files/ 2>/dev/null || true
fi

if [[ -d database/seeders/assets/documents ]]; then
  mkdir -p public/documents
  cp -rn database/seeders/assets/documents/. public/documents/ 2>/dev/null || true
fi

# ── Durable public uploads (news images, partners, media library, …) ─────────
# NEVER rm -rf storage/app/public or news/images — user uploads live here and
# must survive redeploys via a Railway volume or PUBLIC_DISK_DRIVER=s3.
# See docs/deployment/public-media-storage.md
PUBLIC_STORAGE_ROOT="${PUBLIC_DISK_ROOT:-storage/app/public}"
mkdir -p \
  "${PUBLIC_STORAGE_ROOT}/news/images" \
  "${PUBLIC_STORAGE_ROOT}/partners" \
  "${PUBLIC_STORAGE_ROOT}/media/photos/library" \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

php artisan optimize:clear

# storage:link — failures must be visible (do not swallow stderr forever).
if [[ -L public/storage ]]; then
  echo "public/storage symlink OK -> $(readlink public/storage)"
elif [[ -e public/storage ]]; then
  echo "WARNING: public/storage exists but is not a symlink; leaving as-is." >&2
else
  if php artisan storage:link; then
    echo "public/storage symlink created via artisan storage:link"
  else
    echo "WARNING: php artisan storage:link failed; attempting manual symlink." >&2
    if ln -sfn "$(cd "${PUBLIC_STORAGE_ROOT}" && pwd)" public/storage; then
      echo "public/storage symlink created manually -> $(readlink public/storage)"
    else
      echo "ERROR: could not create public/storage link; uploaded media may 404." >&2
    fi
  fi
fi

PUBLIC_DRIVER="${PUBLIC_DISK_DRIVER:-local}"
if [[ "${PUBLIC_DRIVER}" == "local" ]]; then
  if [[ -n "${PUBLIC_STORAGE_PERSISTENT:-}" ]]; then
    echo "PUBLIC_STORAGE_PERSISTENT=${PUBLIC_STORAGE_PERSISTENT}: treating public uploads as durable."
  elif command -v findmnt >/dev/null 2>&1 && findmnt -T "${PUBLIC_STORAGE_ROOT}" >/dev/null 2>&1; then
    echo "Public storage mount detected at ${PUBLIC_STORAGE_ROOT}"
  else
    echo "WARNING: Public uploads disk looks ephemeral (no Railway volume / PUBLIC_STORAGE_PERSISTENT)." >&2
    echo "         News images and other Filament uploads will disappear on redeploy." >&2
    echo "         Attach a volume at /app/storage/app/public or set PUBLIC_DISK_DRIVER=s3." >&2
    echo "         See docs/deployment/public-media-storage.md" >&2
  fi
else
  echo "Public disk driver=${PUBLIC_DRIVER} (object storage for durable media)."
fi

php artisan optimize

# WEB_RUNTIME=frankenphp (default) or artisan (immediate rollback, no rebuild).
# WEB_THREADS is the FrankenPHP thread pool. Each thread holds one database
# connection while a request is running. 32 threads × 2 replicas = 64, plus the
# queue worker, stays under 80% of Postgres max_connections (500 → 400).
runtime="${WEB_RUNTIME:-frankenphp}"
case "${runtime}" in
  artisan)
    # The built-in server ignores PHP_CLI_SERVER_WORKERS unless reload is off.
    echo "WEB_RUNTIME=artisan (rollback): php artisan serve --no-reload on port ${PORT:-8080} workers=${PHP_CLI_SERVER_WORKERS:-1}"
    exec php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-8080}"
    ;;
  frankenphp)
    ;;
  *)
    echo "FATAL: WEB_RUNTIME must be frankenphp or artisan (got ${runtime})." >&2
    exit 1
    ;;
esac

if ! command -v frankenphp >/dev/null 2>&1; then
  echo "FATAL: frankenphp is not on PATH. Set WEB_RUNTIME=artisan to roll back." >&2
  exit 1
fi

threads="${WEB_THREADS:-32}"
if [[ "${threads}" =~ ^[0-9]+$ ]] && (( threads > 160 )); then
  echo "WARNING: WEB_THREADS=${threads} can exceed 80% of Postgres max_connections with two replicas." >&2
fi

echo "WEB_RUNTIME=frankenphp threads=${threads} port=${PORT:-8080}"
export WEB_THREADS="${threads}"
cd /app
exec frankenphp run --config /app/Caddyfile
