#!/usr/bin/env bash
# Dispatch entrypoint: one railpack start command, route by Railway service name.
set -euo pipefail

case "${RAILWAY_SERVICE_NAME:-}" in
  kafaat-worker-staging)
    exec bash railway/run-worker.sh
    ;;
  kafaat-scheduler-staging)
    exec bash railway/run-scheduler.sh
    ;;
  kafaat-web-staging)
    bash railway/predeploy.sh
    exec bash railway/run-web.sh
    ;;
  *)
    echo "Unknown RAILWAY_SERVICE_NAME=${RAILWAY_SERVICE_NAME:-<unset>}; defaulting to web." >&2
    bash railway/predeploy.sh
    exec bash railway/run-web.sh
    ;;
esac
