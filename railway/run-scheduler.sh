#!/usr/bin/env bash
# Optional scheduler-only process. Production runs schedule:work inside the worker.
#
# Deploy only if a service sets:
#   RAILWAY_START_MODE=scheduler
#   Config-as-Code: railway/configs/scheduler.railway.json
set -euo pipefail

php artisan optimize:clear

# Long-running scheduler process; Railway restarts on crash via restartPolicy.
exec php artisan schedule:work --no-interaction
