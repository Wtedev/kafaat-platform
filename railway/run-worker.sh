#!/usr/bin/env bash
# Queue worker and cleanup scheduler for Railway (one container, no public domain).
# If queue:work or schedule:work exits, this script exits and Railway restarts it.
#
# Deploy as a separate Railway service with:
#   RAILWAY_START_MODE=worker
#   Config-as-Code: railway/configs/worker.railway.json
# Do NOT attach a public domain; do NOT run preDeploy migrations here.
set -euo pipefail

php artisan optimize:clear

queue_pid=
schedule_pid=

cleanup() {
  local status=$?
  trap - EXIT INT TERM
  if [[ -n "${queue_pid}" ]]; then
    kill "${queue_pid}" 2>/dev/null || true
  fi
  if [[ -n "${schedule_pid}" ]]; then
    kill "${schedule_pid}" 2>/dev/null || true
  fi
  wait "${queue_pid}" "${schedule_pid}" 2>/dev/null || true
  exit "${status}"
}
trap cleanup EXIT INT TERM

# --sleep=1: poll promptly for privacy exports / mail jobs
# --tries=3: match failed_jobs expectations in health checks
# --timeout=120: long enough for export ZIP generation
# --max-time=3600: recycle the process hourly (Railway restarts the service)
php artisan queue:work \
  --queue=default \
  --sleep=1 \
  --tries=3 \
  --timeout=120 \
  --max-time=3600 \
  --memory=256 &
queue_pid=$!

php artisan schedule:work --no-interaction &
schedule_pid=$!

while kill -0 "${queue_pid}" 2>/dev/null && kill -0 "${schedule_pid}" 2>/dev/null; do
  sleep 2
done

exit 1
