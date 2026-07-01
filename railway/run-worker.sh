#!/usr/bin/env bash
# Dedicated queue worker for Railway (persistent service, no public domain).
set -euo pipefail

php artisan optimize:clear

exec php artisan queue:work \
  --queue=default \
  --sleep=3 \
  --tries=3 \
  --timeout=120 \
  --max-time=3600
