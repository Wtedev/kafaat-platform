#!/usr/bin/env bash
# Dedicated queue worker for Railway (persistent service, no public domain).
#
# Deploy as a separate Railway service with:
#   RAILWAY_START_MODE=worker
#   Config-as-Code: railway/configs/worker.railway.json
# Do NOT attach a public domain; do NOT run preDeploy migrations here.
set -euo pipefail

php artisan optimize:clear

# --sleep=1: poll promptly for privacy exports / mail jobs
# --tries=3: match failed_jobs expectations in health checks
# --timeout=120: long enough for export ZIP generation
# --max-time=3600: recycle the process hourly (Railway restarts the service)
exec php artisan queue:work \
  --queue=default \
  --sleep=1 \
  --tries=3 \
  --timeout=120 \
  --max-time=3600 \
  --memory=256
