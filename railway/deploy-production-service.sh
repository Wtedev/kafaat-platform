#!/usr/bin/env bash
# Redeploy a single Production service role: web | worker | scheduler
# Usage: bash railway/deploy-production-service.sh web
set -euo pipefail

ROLE="${1:-}"
ENVIRONMENT=production

case "$ROLE" in
  web) SERVICE=kafaat-platform ;;
  worker) SERVICE=kafaat-worker ;;
  scheduler) SERVICE=kafaat-scheduler ;;
  *)
    echo "Usage: $0 web|worker|scheduler" >&2
    exit 1
    ;;
esac

if ! railway whoami >/dev/null 2>&1; then
  echo "Run: railway login" >&2
  exit 1
fi

railway environment "$ENVIRONMENT" >/dev/null
echo "Redeploying Production ${ROLE} (${SERVICE}) from main..."
railway redeploy --environment "$ENVIRONMENT" --service "$SERVICE" --yes --from-source
echo "Done."
