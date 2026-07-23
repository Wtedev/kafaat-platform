#!/usr/bin/env bash
# Redeploy Production web + queue worker + scheduler from latest main.
# Does not push git; does not create Railway services (create those in the UI first).
set -euo pipefail

ENVIRONMENT=production
SERVICES=(
  kafaat-platform
  kafaat-worker
  kafaat-scheduler
)

if ! railway whoami >/dev/null 2>&1; then
  echo "Run: railway login" >&2
  exit 1
fi

railway environment "$ENVIRONMENT" >/dev/null

echo "Redeploying Production services from main..."
failed=0
for svc in "${SERVICES[@]}"; do
  echo "--- ${svc} ---"
  if ! railway redeploy --environment "$ENVIRONMENT" --service "$svc" --yes --from-source; then
    echo "WARNING: redeploy failed for ${svc} (service may not exist yet — create it in Railway UI)." >&2
    failed=1
  fi
done

echo "Done. Production URL: https://kafaat-platform-production.up.railway.app"
echo "Verify: open /up on web; check worker/scheduler logs for queue:work / schedule:work."
if [[ "$failed" -ne 0 ]]; then
  echo "One or more services failed — see docs/audits/railway-infra-implementation.md" >&2
  exit 1
fi
