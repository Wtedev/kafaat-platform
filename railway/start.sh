#!/usr/bin/env bash
# Dispatch entrypoint: one railpack start command, route by service role.
set -euo pipefail

mode="${RAILWAY_START_MODE:-}"

if [[ -z "$mode" ]]; then
  case "${RAILWAY_SERVICE_NAME:-}" in
    kafaat-worker-staging) mode=worker ;;
    kafaat-scheduler-staging) mode=scheduler ;;
    kafaat-web-staging|kafaat-platform) mode=web ;;
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
    bash railway/predeploy.sh
    exec bash railway/run-web.sh
    ;;
  *)
    echo "FATAL: Unknown start mode (RAILWAY_START_MODE=${RAILWAY_START_MODE:-<unset>}, RAILWAY_SERVICE_NAME=${RAILWAY_SERVICE_NAME:-<unset>})" >&2
    exit 1
    ;;
esac
