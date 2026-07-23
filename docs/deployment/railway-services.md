# Railway Services Reference

## Production (target topology)

| Service | Type | Branch | Config-as-Code | Start | Notes |
|---------|------|--------|----------------|-------|-------|
| `kafaat-platform` | Web | `main` | root `railway.json` / `railway.toml` (or `railway/configs/web.railway.json`) | `start.sh` → web | Healthcheck `GET /up`; preDeploy migrations |
| `kafaat-worker` | Worker | `main` | **`railway/configs/worker.railway.json`** | `start.sh` → worker | No public domain; `RAILWAY_START_MODE=worker` |
| `kafaat-scheduler` | Scheduler | `main` | **`railway/configs/scheduler.railway.json`** | `start.sh` → scheduler | No public domain; `RAILWAY_START_MODE=scheduler` |
| Postgres | PostgreSQL | — | — | — | Shared by all app services |
| Public media volume | Volume | — | — | — | Mount `/app/storage/app/public` on **web** (+ `PUBLIC_STORAGE_PERSISTENT=1`) |
| Private docs volume | Volume | — | — | — | Mount `/app/storage/app/private-documents` **or** S3 bucket |

Legacy failed worker attempt (`poetic-reprieve`) is still recognized by `railway/start.sh` as a worker name so it can be recovered without rename, but prefer `kafaat-worker`.

**Public uploads:** see `docs/deployment/public-media-storage.md`.

Minute-level schedules require the dedicated scheduler service (`schedule:work`). Railway Cron (5-minute minimum) is **not** sufficient.

## Staging (parity)

| Component | Decision |
|-----------|----------|
| Web | `kafaat-web-staging`, health `/up`, preDeploy `railway/predeploy.sh` |
| Worker | `kafaat-worker-staging`, `railway/configs/worker.railway.json` |
| Scheduler | `kafaat-scheduler-staging`, `railway/configs/scheduler.railway.json` |
| PostgreSQL | Staging instance |
| Private storage | Railway Bucket / volume; `PRIVATE_DOCUMENTS_DISK=s3` or volume path |

## Scripts

| File | Purpose |
|------|---------|
| `railway/predeploy.sh` | Migrations + content seeders (**web preDeploy only**) |
| `railway/start.sh` | Dispatch by `RAILWAY_START_MODE` or service name |
| `railway/run-web.sh` | HTTP server + storage link |
| `railway/run-worker.sh` | `queue:work` |
| `railway/run-scheduler.sh` | `schedule:work` |
| `railway/deploy-production.sh` | Redeploy web + worker + scheduler |
| `railway/deploy-production-service.sh` | Redeploy one role |
| `railway/verify-production.sh` | Post-deploy health / mail / `/up` checks |
| `railway/verify-staging.sh` | Staging post-deploy checks |
| `railway/smoke-test-staging.sh` | Public HTTP smoke |

## Scheduler tasks (require every-minute runner)

- `news:publish-scheduled` — every minute
- `training:publish-scheduled` — every minute
- `inbox:dispatch-training-milestones` — hourly
- `privacy:purge-expired-exports` — 03:30 Asia/Riyadh
- `privacy:apply-retention` — 04:00 Asia/Riyadh
- `error-pages:prune` — 04:30 Asia/Riyadh

## Operator checklist (create services in Railway UI)

See `docs/audits/railway-infra-implementation.md`.
