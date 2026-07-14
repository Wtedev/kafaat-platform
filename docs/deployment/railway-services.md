# Railway Services Reference

## Production (read-only inventory)

| Service | Type | Branch | Status | Notes |
|---------|------|--------|--------|-------|
| kafaat-platform | Web | `main` | Online | `railpack.json` + serve on `$PORT` |
| poetic-reprieve | Worker attempt | `main` | Deploy failed | queue:work only — not production-ready |
| Postgres | PostgreSQL | — | Online | Shared by production web |
| Bucket | — | — | **None** | CV on local/ephemeral disk |

Production start (Web): migrate in legacy config; current repo branch uses `railway/run-web.sh` without inline migrate.

Production **does not** run a dedicated Scheduler service — scheduled tasks (`news:publish-scheduled`, retention, export purge) may not run reliably.

## Staging (target)

| Component | Decision |
|-----------|----------|
| Web | `bash railway/run-web.sh`, health `/up` (also in `railway.json` healthcheckPath), preDeploy `railway/predeploy.sh` |
| Worker | `bash railway/run-worker.sh`, persistent, no domain |
| Scheduler | `bash railway/run-scheduler.sh` (every-minute tasks) |
| PostgreSQL | New instance in `staging` environment |
| Private storage | Railway Bucket `kafaat-private-staging`, `PRIVATE_DOCUMENTS_DISK=s3` |

## Scripts

| File | Purpose |
|------|---------|
| `railway/predeploy.sh` | Migrations + permission seed (Web preDeploy only) |
| `railway/run-web.sh` | HTTP server |
| `railway/run-worker.sh` | Queue worker |
| `railway/run-scheduler.sh` | `schedule:run` loop |
| `railway/verify-staging.sh` | Post-deploy checks |
| `railway/smoke-test-staging.sh` | Public HTTP smoke |

## Scheduler tasks (require every-minute runner)

- `news:publish-scheduled` — every minute
- `training:publish-scheduled` — every minute
- `inbox:dispatch-training-milestones` — hourly
- `privacy:purge-expired-exports` — 03:30 Asia/Riyadh
- `privacy:apply-retention` — 04:00 Asia/Riyadh

Railway Cron (5-minute minimum) is **not** sufficient for the minute-level tasks.
