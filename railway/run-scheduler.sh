#!/usr/bin/env bash
# Laravel scheduler — required because several tasks run every minute.
#
# Railway Cron (5-minute minimum) is insufficient for:
#   news:publish-scheduled, training:publish-scheduled
#
# Deploy as a separate Railway service with:
#   RAILWAY_START_MODE=scheduler
#   Config-as-Code: railway/configs/scheduler.railway.json
# Prefer schedule:work (Laravel 11+/12) over a custom sleep loop.
set -euo pipefail

php artisan optimize:clear

# Long-running scheduler process; Railway restarts on crash via restartPolicy.
exec php artisan schedule:work --no-interaction
