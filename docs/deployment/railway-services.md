# Railway Services Reference

## Production (target topology)

| Service | Type | Branch | Config-as-Code | Start | Notes |
|---------|------|--------|----------------|-------|-------|
| `kafaat-platform` | Web | `main` | root `railway.json` / `railway.toml` (or `railway/configs/web.railway.json`) | `start.sh` → web | Healthcheck `GET /up`; preDeploy migrations |
| `kafaat-worker` | Worker | `main` | **`railway/configs/worker.railway.json`** | `start.sh` → worker | No public domain; `RAILWAY_START_MODE=worker`. Runs `queue:work` and `schedule:work` together |
| `kafaat-scheduler` | Scheduler | — | **`railway/configs/scheduler.railway.json`** | `start.sh` → scheduler | Not used in production. Cleanup schedules run inside the worker |
| Postgres | PostgreSQL | — | — | — | Shared by all app services |
| Public media volume | Volume | — | — | — | Mount `/app/storage/app/public` on **web** (+ `PUBLIC_STORAGE_PERSISTENT=1`) |
| Private docs volume | Volume | — | — | — | Mount `/app/storage/app/private-documents` **or** S3 bucket |

Legacy failed worker attempt (`poetic-reprieve`) is still recognized by `railway/start.sh` as a worker name so it can be recovered without rename, but prefer `kafaat-worker`.

**Public uploads:** see `docs/deployment/public-media-storage.md`.

Production cleanup schedules run inside the worker (`schedule:work` next to `queue:work`). If either process exits, the worker script exits and Railway restarts the container. Railway Cron is not used.

## Staging

Paused. See `docs/deployment/railway-staging.md`. Do not deploy there.

## Scripts

| File | Purpose |
|------|---------|
| `railway/predeploy.sh` | Migrations, role catalog, and content seeders when sources changed (**web preDeploy only**) |
| `railway/start.sh` | Dispatch by `RAILWAY_START_MODE` or service name |
| `railway/run-web.sh` | HTTP server + storage link. Does not run predeploy |
| `railway/run-worker.sh` | `queue:work` and `schedule:work` |
| `railway/run-scheduler.sh` | `schedule:work` only (not a production service) |
| `railway/deploy-production.sh` | Redeploy web + worker + scheduler |
| `railway/deploy-production-service.sh` | Redeploy one role |
| `railway/verify-production.sh` | Post-deploy health / mail / `/up` checks |
| `railway/verify-staging.sh` | Staging post-deploy checks |
| `railway/smoke-test-staging.sh` | Public HTTP smoke |

## Scheduler tasks (worker)

- `auth:purge-expired-pending-registrations` — hourly
- `privacy:purge-expired-exports` — 03:30 Asia/Riyadh
- `staff-ui:purge-expired-beneficiary-exports` — 03:45 Asia/Riyadh
- `privacy:apply-retention` — 04:00 Asia/Riyadh
- `error-pages:prune` — 04:30 Asia/Riyadh

## Operator checklist (create services in Railway UI)

See `docs/audits/railway-infra-implementation.md`.
