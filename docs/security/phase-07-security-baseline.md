# Phase 07 — Security Baseline

Captured at start of production hardening on branch `feature/privacy-compliance-phase-07-production-hardening`.

## Test baseline (pre-hardening)

| Metric | Value |
|--------|-------|
| Tests | 197 |
| Assertions | 524 |
| Duration | ~52s |
| Random seed gate | 7001 — OK |

## Runtime

| Component | Value |
|-----------|-------|
| PHP (composer) | ^8.4 |
| Laravel | ^13.0 |
| PostgreSQL (target) | via `pdo_pgsql` |
| PHPUnit | ^12.5 |

## Configuration snapshot (local `.env.example` defaults)

| Setting | Default |
|---------|---------|
| Queue | `database` |
| Session | `file` |
| Cache | `file` |
| Private documents | `private_documents` disk |
| Mail | `resend` |

## Deployment blockers (known before Phase 07)

1. **Queue worker** — privacy export generation requires a running worker in production (`QUEUE_CONNECTION` must not be `sync`).
2. **Scheduler cron** — `* * * * * php artisan schedule:run` required for retention/export purge.
3. **Trusted proxy CIDRs** — Railway uses reverse proxy; document explicit `TRUSTED_HOSTS` when hostname pinning is enabled.
4. **Backup restore test** — not verified in CI; manual runbook required before production.
5. **Admin MFA** — not implemented; organizational decision pending.

## Post-hardening target

Security suite under `tests/Feature/Security/` plus full suite 197+ tests, seeds 7001–7003.
