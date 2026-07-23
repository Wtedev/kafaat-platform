#!/usr/bin/env bash
# Dispatch entrypoint: one Railpack start command, route by service role.
#
# Preferred: set RAILWAY_START_MODE=web|worker|scheduler on each Railway service.
# Fallback: infer from RAILWAY_SERVICE_NAME (production + staging names below).
#
# Worker/scheduler must NOT use the root railway.json healthcheck (/up) or
# preDeploy — point those services at railway/configs/*.railway.json instead.
# See docs/audits/railway-infra-implementation.md
set -euo pipefail

mode="${RAILWAY_START_MODE:-}"

if [[ -z "$mode" ]]; then
  case "${RAILWAY_SERVICE_NAME:-}" in
    # Production
    kafaat-worker|kafaat-worker-production|poetic-reprieve) mode=worker ;;
    kafaat-scheduler|kafaat-scheduler-production) mode=scheduler ;;
    kafaat-platform) mode=web ;;
    # Staging
    kafaat-worker-staging) mode=worker ;;
    kafaat-scheduler-staging) mode=scheduler ;;
    kafaat-web-staging) mode=web ;;
    *) mode=unknown ;;
  esac
fi

case "$mode" in
  worker)
    echo "Railway start: worker (queue)" >&2
    exec bash railway/run-worker.sh
    ;;
  scheduler)
    echo "Railway start: scheduler loop" >&2
    exec bash railway/run-scheduler.sh
    ;;
  web)
    echo "Railway start: web" >&2
    exec bash railway/run-web.sh
    ;;
  *)
    echo "FATAL: Unknown start mode (RAILWAY_START_MODE=${RAILWAY_START_MODE:-<unset>}, RAILWAY_SERVICE_NAME=${RAILWAY_SERVICE_NAME:-<unset>})" >&2
    echo "Set RAILWAY_START_MODE=web|worker|scheduler, or name the service per docs/deployment/railway-services.md" >&2
    exit 1
    ;;
esac
