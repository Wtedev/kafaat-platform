#!/usr/bin/env bash
# Laravel scheduler loop — required because tasks run every minute.
set -euo pipefail

php artisan optimize:clear

while true; do
  php artisan schedule:run --no-interaction
  sleep 60
done
