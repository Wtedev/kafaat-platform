# Monitoring and Alerting

## Health checks

| Check | Command / endpoint | Alert if |
|-------|-------------------|----------|
| App up | `/up` | non-200 |
| System health | `php artisan system:health` | exit ≠ 0 |
| Failed jobs | health check `failed_jobs` | count ≥ threshold |
| Scheduler | `privacy:retention-status` | stale execute |

Visitor HTML error pages (403 / 404 / 419 / 429 / 500 / 503, plus gateway statuses) are stored in `error_page_visits` and shown at `/admin/error-page-stats`. Railway’s own «Application failed to respond» edge page is **not** counted — see `docs/operations/custom-error-pages.md` and `RAILWAY_ERROR_HANDLING_SETUP.md`.

## Security metrics (no PII)

- `auth.login_failed` spike
- `auth.login_blocked` rate
- `privacy_export.download_denied`
- `privacy_export.verification_failed`
- `retention.concurrent_run_blocked`

## Infrastructure

- Disk usage on private volume
- Queue backlog age
- DB connectivity
- Backup job success / age

Owner: System Operator. Escalation: Security Officer.

See `docs/operations/security-incident-response.md`.
