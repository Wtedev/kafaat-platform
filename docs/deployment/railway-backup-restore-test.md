# Railway Staging — Backup & Restore Test

## Scope

Isolated restore validation for **staging PostgreSQL only**. Never restore into Production.

## Prerequisites

- Staging environment deployed and healthy
- `pg_dump` / `pg_restore` or Railway backup feature enabled for staging Postgres
- Separate restore database or `restore-test` environment (requires cost approval)

## Procedure

1. **Pause** Worker and Scheduler in staging during restore test.
2. Create PostgreSQL backup from staging (Railway dashboard or `pg_dump` via `railway run`).
3. Provision **separate** Postgres (restore-test DB or environment).
4. Restore backup into isolated database.
5. Point a throwaway Web service at restore DB with mail disabled.
6. Run migrations if needed: `php artisan migrate --force`.
7. Run `php artisan system:health`.
8. Execute privacy reconciliation per `docs/privacy/post-restore-privacy-reconciliation.md`:
   - Anonymized accounts remain anonymized
   - Expired exports not resurrected
   - Deleted CV paths not re-linked
9. Session invalidation if breach scenario simulated.
10. Smoke test on restore instance only.
11. Record **RPO** (backup age) and **RTO** (time to healthy).
12. Tear down restore-test resources.

## Bucket

PostgreSQL backup does **not** include private files. Document separate bucket backup/sync for CV and export ZIPs.

## Deployment blocker

Until restore test completes successfully: **Production remains blocked** even if staging app runs.

## Status

| Step | Done |
|------|------|
| Staging backup created | Pending staging environment |
| Isolated restore | Pending |
| Privacy reconciliation | Pending |
| RPO/RTO recorded | Pending |
