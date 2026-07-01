#!/usr/bin/env bash
# Dispatch entrypoint: one railpack start command, route by service role.
set -euo pipefail

mode="${RAILWAY_START_MODE:-}"

if [[ -z "$mode" ]]; then
  case "${RAILWAY_SERVICE_NAME:-}" in
    kafaat-worker-staging) mode=worker ;;
    kafaat-scheduler-staging) mode=scheduler ;;
    kafaat-web-staging) mode=web ;;
    *) mode=unknown ;;
  esac
fi

case "$mode" in
  worker)
    exec bash railway/run-worker.sh
    ;;
  scheduler)
    exec bash railway/run-scheduler.sh
    ;;
  web)
    bash railway/predeploy.sh
    exec bash railway/run-web.sh
    ;;
  *)
    echo "FATAL: Unknown start mode (RAILWAY_START_MODE=${RAILWAY_START_MODE:-<unset>}, RAILWAY_SERVICE_NAME=${RAILWAY_SERVICE_NAME:-<unset>})" >&2
    exit 1
    ;;
esac
