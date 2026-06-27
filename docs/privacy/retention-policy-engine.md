# Retention Policy Engine

## Overview

The retention engine applies **active, approved policies** to eligible records. No deletion runs without:

1. An **active** policy with an approved `retention_period_days`
2. `effective_at` reached
3. A resource handler registered in `RetentionResourceCatalog`
4. For sensitive resources: preview, activation permission, and optional manual approval flag

## Core components

| Component | Role |
|-----------|------|
| `RetentionResourceCatalog` | Allowlisted resource definitions (no free-form table/model names) |
| `RetentionPolicyEngine` | Preview, execute, activate, cutoff calculation, locks, run logging |
| `RetentionHandlerRegistry` | Maps resource codes to handlers |
| `RetentionExceptionChecker` | Legal holds / operational exceptions |
| `retention_runs` / `retention_run_items` | Permanent audit trail without PII |

## Policy lifecycle

```
draft → (preview) → active → inactive | superseded
```

- **Draft**: editable via Filament with `retention_policies.update_draft`
- **Active**: immutable effect; superseded when a new policy activates for the same resource context
- **Activation** requires a fresh preview (`PRIVACY_RETENTION_PREVIEW_FRESHNESS_HOURS`, default 24h) with zero preview failures

## Actions

| Action | Meaning |
|--------|---------|
| `delete` | Remove record and associated files (when applicable) |
| `anonymize` | Irreversible field scrubbing per handler allowlist |
| `retain_restricted` | No scheduled disposal; restricted access only |

## Commands

```bash
php artisan privacy:retention-preview [--policy=UUID] [--resource=code] [--batch=N]
php artisan privacy:apply-retention [--policy=UUID] [--resource=code] [--batch=N] [--max-items=N] [--resume=UUID] [--dry-run]
php artisan privacy:retention-status
php artisan privacy:purge-expired-exports   # wrapper over export handler + active policy
```

## Safety rules

- No `truncate`, no hard user delete, no deletion without policy
- Audit/security critical events protected via config lists
- Certificates, attendance, registrations default to `retain_restricted`
- Concurrent runs blocked per policy via distributed lock

## Configuration

See `config/privacy_retention.php` and `.env` keys:

- `PRIVACY_RETENTION_PREVIEW_FRESHNESS_HOURS`
- `PRIVACY_RETENTION_BATCH_SIZE`
- `PRIVACY_RETENTION_LOCK_TTL`
