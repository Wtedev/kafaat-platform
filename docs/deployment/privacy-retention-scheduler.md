# Privacy Retention Scheduler

## Scheduled tasks

| Command | Schedule | Timezone |
|---------|----------|----------|
| `privacy:purge-expired-exports` | Daily 03:30 | `Asia/Riyadh` (via `config/app.timezone`) |
| `privacy:apply-retention` | Daily 04:00 | same |

Both use `withoutOverlapping()`.

## Cron requirement

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Health check

```bash
php artisan privacy:retention-status
```

Reports active policy count and last successful execute/preview timestamps (no PII).

## Deployment blockers

1. **Cron / scheduler** must run every minute
2. Policies for operational resources must be **activated** after preview (export purge policy seeded active by documented operational approval)
3. OTP/session/password-reset policies ship as **draft** — activate only after organizational sign-off

## Queue

Large runs may use queue when count exceeds `PRIVACY_RETENTION_QUEUE_THRESHOLD`. Queue worker deployment is required if queue dispatch is enabled in future thresholds.
