#!/usr/bin/env bash
# Redeploy Production web from latest main commit.
set -euo pipefail

ENVIRONMENT=production
SERVICE=kafaat-platform

if ! railway whoami >/dev/null 2>&1; then
  echo "Run: railway login" >&2
  exit 1
fi

railway environment "$ENVIRONMENT" >/dev/null

echo "Redeploying Production service ${SERVICE} from main..."
railway redeploy --environment "$ENVIRONMENT" --service "$SERVICE" --yes --from-source

echo "Done. Production URL: https://kafaat-platform-production.up.railway.app"
