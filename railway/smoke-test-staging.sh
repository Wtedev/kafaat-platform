#!/usr/bin/env bash
# HTTP smoke checks against staging domain (no auth, no PII).
set -euo pipefail

BASE_URL="${STAGING_URL:-${APP_URL:-}}"
if [[ -z "${BASE_URL}" ]]; then
  echo "Set STAGING_URL or APP_URL to the staging HTTPS domain." >&2
  exit 1
fi

BASE_URL="${BASE_URL%/}"

check() {
  local path="$1"
  local code
  code=$(curl -sS -o /dev/null -w '%{http_code}' "${BASE_URL}${path}")
  echo "${path} -> HTTP ${code}"
  if [[ "${code}" -ge 500 ]]; then
    return 1
  fi
}

echo "Smoke test: ${BASE_URL}"
check "/"
check "/login"
check "/register"
check "/up"
check "/privacy"

echo "Smoke test complete (5xx on any route fails the script)."
