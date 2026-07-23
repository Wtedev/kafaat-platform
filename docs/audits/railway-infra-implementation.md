# Railway infrastructure implementation notes

| Field | Value |
|-------|-------|
| **Date** | 2026-07-23 |
| **Scope** | Railway deploy scripts, config-as-code, env documentation |
| **Source** | `docs/audits/production-readiness-audit.md` (infra items only) |
| **Constraint** | No application feature rewrites; web deploy must keep working |

This file records **why** each infrastructure change was made. Operational steps for the Railway console are at the end.

---

## Decisions

### 1. Queue worker as a separate Railway service

**Decision:** Keep `railway/run-worker.sh` and route via `railway/start.sh` when `RAILWAY_START_MODE=worker` or the service is named `kafaat-worker` / `kafaat-worker-production` (plus legacy `poetic-reprieve`).

**Why:** Privacy export ZIPs, retention jobs, and mail notifications use the database queue. Without a persistent `queue:work` process they stall. Embedding a worker inside the web start script would compete with HTTP and break zero-downtime assumptions.

**Flags:** `--sleep=1 --tries=3 --timeout=120 --max-time=3600 --memory=256` — prompt polling for OTP/export jobs, bounded retries, hourly process recycle so Railway can apply image updates cleanly.

**Config:** `railway/configs/worker.railway.json` (and `.toml`) — **no** `healthcheckPath`, **no** `preDeployCommand`. A worker does not listen on `$PORT`; inheriting root `railway.json` would mark deploys failed on `/up` and race migrations.

### 2. Scheduler as a separate Railway service

**Decision:** Replace the custom `schedule:run` + `sleep 60` loop with `php artisan schedule:work` in `railway/run-scheduler.sh`.

**Why:** Laravel 11+/12 provides a long-running scheduler that wakes every minute. Several app schedules are **every minute** (`news:publish-scheduled`, `training:publish-scheduled`); Railway’s built-in Cron floor (5 minutes) cannot replace this service.

**Config:** `railway/configs/scheduler.railway.json` — same isolation rules as the worker (no HTTP healthcheck, no preDeploy).

### 3. Durable storage (volumes / S3) — config and docs only

**Decision:** Document mount paths and env vars in `.env.example` and deployment docs; do not change Laravel disk PHP logic.

| Disk | Path / setting |
|------|----------------|
| Public media | Volume mount `/app/storage/app/public` + `PUBLIC_STORAGE_PERSISTENT=1`, **or** `PUBLIC_DISK_DRIVER=s3` |
| Private CVs/exports | Volume mount `/app/storage/app/private-documents`, **or** `PRIVATE_DOCUMENTS_DISK=s3` |

**Why:** Railpack app root is `/app`. Ephemeral container FS loses uploads on redeploy. Volumes are the lowest-friction fix; S3 remains the better multi-replica option. App code already supports `PUBLIC_DISK_*` and `PRIVATE_DOCUMENTS_DISK`.

### 4. Production mail configuration

**Decision:** Keep `.env.example` default `MAIL_MAILER=resend` and document that production **must not** use `log`/`array`. Add optional SMTP variable stubs. `railway/verify-production.sh` fails closed if `MAIL_MAILER` is `log` or `array`.

**Why:** OTP and notifications require a real transport. The audit found a local production template using `log`; that must not be copied to Railway.

### 5. Health checks

**Decision:** Web services keep `healthcheckPath = "/up"` and `healthcheckTimeout = 120` in root `railway.json` / `railway.toml` (and `railway/configs/web.*`). Worker/scheduler configs omit healthchecks.

**Why:** Laravel already exposes `/up`. Wiring it prevents routing traffic to a booting web container. Applying the same check to worker/scheduler would false-fail those services.

### 6. Production deployment scripts

**Decision:**

| Script | Role |
|--------|------|
| `railway/deploy-production.sh` | Redeploy `kafaat-platform`, `kafaat-worker`, `kafaat-scheduler` |
| `railway/deploy-production-service.sh` | Redeploy one role |
| `railway/verify-production.sh` | Env/mail/health/`/up` smoke |
| `railway/predeploy.sh` | Remains **web-only**; header documents that worker/scheduler must not run it |

**Why:** Staging already redeploys three services; production previously redeployed web only, which left worker/scheduler stale or missing.

### 7. Config-as-code file layout

**Decision:** Keep root `railway.json` for backward-compatible **web** defaults; add `railway.toml` mirror; add per-role files under `railway/configs/`.

**Why:** Railway config-as-code is **per service**. Custom path in the UI (`/railway/configs/worker.railway.json`) lets one repo serve three roles without breaking the existing web service that already reads the root file.

### 8. What we deliberately did not change

- No switch away from `php artisan serve` (audit High, larger change).
- No edits to `bootstrap/app.php`, session/CORS middleware, or trusted proxies (parallel agent / out of infra scope).
- No Dockerfile (Railpack remains the builder via `railpack.json`).
- No Redis requirement (document `CACHE_STORE=database` preference only).

---

## Operator steps (Railway UI)

Do these once per environment (production first).

### A. Create worker service

1. Project → **production** → **New** → **GitHub Repo** (same repo/branch as web).
2. Name the service `kafaat-worker` (recommended).
3. Settings → **Config-as-Code** path: `/railway/configs/worker.railway.json` (or `.toml`).
4. Variables: share DB/`APP_KEY`/queue/mail secrets with web (Railway variable references). Set `RAILWAY_START_MODE=worker`.
5. **Do not** generate a public domain.
6. Deploy; confirm logs show `queue:work`.

### B. Create scheduler service

1. Same as worker, name `kafaat-scheduler`.
2. Config-as-Code: `/railway/configs/scheduler.railway.json`.
3. Set `RAILWAY_START_MODE=scheduler`.
4. No public domain; confirm logs show `schedule:work`.

### C. Attach durable volumes (if not using S3)

On **web** (`kafaat-platform`):

1. Volumes → Add volume → mount `/app/storage/app/public`.
2. Set `PUBLIC_STORAGE_PERSISTENT=1`.
3. Volumes → Add volume → mount `/app/storage/app/private-documents` (unless `PRIVATE_DOCUMENTS_DISK=s3`).
4. Redeploy web; upload a news image and a CV; redeploy again; confirm files remain.

If the worker writes private exports to local disk, attach the **same** private volume path to the worker service as well (or use S3 so all roles share object storage).

### D. Production mail + required env

On **all** app services (or shared variable group), set at least:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
- `MAIL_MAILER=resend` (or `smtp`) + provider credentials — **never** `log`
- `IDENTITY_LOOKUP_KEY`, `PRIVATE_DOCUMENTS_DISK`, `FORCE_HTTPS=true`
- `QUEUE_CONNECTION=database` (or redis)
- Prefer `LOG_STACK=stderr` for Railway log drains
- Prefer `CACHE_STORE=database` (or redis) over `file`

Full matrix: `docs/deployment/production-environment-matrix.md`.

### E. Confirm healthcheck on web only

1. Web service settings: healthcheck path `/up` (already in root config).
2. Worker/scheduler: ensure no healthcheck path is set (config files omit it).
3. After deploy: `curl -I https://<host>/up` → 200; or `bash railway/verify-production.sh` via `railway run`.

### F. Redeploy

```bash
bash railway/deploy-production.sh
# or
bash railway/deploy-production-service.sh worker
```

---

## File index

| Path | Purpose |
|------|---------|
| `railway.json` / `railway.toml` | Default web deploy + `/up` healthcheck + preDeploy |
| `railway/configs/web.railway.*` | Explicit web config path |
| `railway/configs/worker.railway.*` | Worker: start only |
| `railway/configs/scheduler.railway.*` | Scheduler: start only |
| `railway/start.sh` | Role dispatcher |
| `railway/run-*.sh` | Role entrypoints |
| `railway/deploy-production*.sh` | Redeploy helpers |
| `railway/verify-production.sh` | Production smoke |
| `.env.example` | Storage / mail / logging / Railway role notes |
| `docs/deployment/railway-services.md` | Service inventory |
