# Railway Staging Environment

Target architecture inside the existing Railway project (no new project).

## Environment

| Item | Value |
|------|-------|
| Environment name | `staging` |
| Git branch | `feature/privacy-compliance-phase-07-production-hardening` |
| Production URL (unchanged) | `https://kafaat-platform-production.up.railway.app` |
| Staging URL | Assigned after `railway domain` on Web service |

## Services

| Service | Role | Start command | Domain |
|---------|------|---------------|--------|
| Web (`kafaat-platform-staging` or similar) | HTTP | `bash railway/run-web.sh` | Yes |
| Worker | Queue | `bash railway/run-worker.sh` | No |
| Scheduler | Cron loop | `bash railway/run-scheduler.sh` | No |
| PostgreSQL | Database | template | No |
| Bucket | Private CV/exports | S3-compatible | No |
| Volume (web) | Public media (`storage/app/public`) | Mount `/app/storage/app/public` | No |

## Isolation rules

- Staging PostgreSQL must **not** reference Production `DATABASE_URL` / `PGHOST`.
- Staging Bucket must **not** share Production credentials.
- `APP_ENV=staging`, `APP_DEBUG=false`.
- `PRIVATE_DOCUMENTS_DISK=s3` with Railway Bucket credentials (Web + Worker share bucket).
- Public Filament/news media: volume at `/app/storage/app/public` + `PUBLIC_STORAGE_PERSISTENT=1` (or `PUBLIC_DISK_DRIVER=s3`). See `docs/deployment/public-media-storage.md`.
- `TRUSTED_HOSTS` = staging domain only.
- Separate `APP_KEY` and `IDENTITY_LOOKUP_KEY` from Production.
- Mail: Resend with test inbox or sandbox — never real beneficiaries.

## Pre-deploy (Web only)

```bash
bash railway/predeploy.sh
```

Runs: `migrate --force`, `RolesAndPermissionsSeeder`, `permission:cache-reset`.

## Variables (keys only)

See `docs/deployment/production-environment-matrix.md` plus:

- `APP_ENV=staging`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`
- `FORCE_HTTPS=true`
- `AWS_*` from Railway Bucket variable references
- `PRIVATE_DOCUMENTS_DISK=s3`

## Verification

```bash
railway run --environment staging --service <WEB> bash railway/verify-staging.sh
STAGING_URL=https://<staging-domain> bash railway/smoke-test-staging.sh
```

## Production

**Do not** deploy this branch to Production until staging sign-off and merge policy approval.
