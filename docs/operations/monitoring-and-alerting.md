# Monitoring and Alerting

## Health checks

| Check | Command / endpoint | Alert if |
|-------|-------------------|----------|
| App up | `/up` | non-200 |
| System health | `php artisan system:health` | exit ≠ 0 |
| Failed jobs | health check `failed_jobs` | count ≥ threshold |
| Scheduler | `privacy:retention-status` | stale execute |

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
