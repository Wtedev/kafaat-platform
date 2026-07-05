#!/usr/bin/env bash
# Redeploy all Staging services from latest branch commit. Production is never touched.
set -euo pipefail

ENVIRONMENT=staging
BRANCH=main
SERVICES=(kafaat-web-staging kafaat-worker-staging kafaat-scheduler-staging)

if ! railway whoami >/dev/null 2>&1; then
  echo "Run: railway login" >&2
  exit 1
fi

railway environment "$ENVIRONMENT" >/dev/null

echo "Redeploying Staging services from ${BRANCH}..."
for svc in "${SERVICES[@]}"; do
  echo "--- ${svc} ---"
  railway redeploy --environment "$ENVIRONMENT" --service "$svc" --yes --from-source
done

echo "Done. Check logs and HTTP smoke test."
