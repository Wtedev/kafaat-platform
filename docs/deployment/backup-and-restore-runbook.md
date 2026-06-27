# Backup and Restore Runbook

## Scope

- PostgreSQL database (primary state)
- Private documents disk (CVs, privacy exports)
- Encryption keys (APP_KEY, IDENTITY_LOOKUP_KEY) — separate secret backup

## Backup requirements (operational)

1. Automated DB backups with encryption at rest (platform responsibility).
2. File storage backup/sync for `PRIVATE_DOCUMENTS_DISK` volume.
3. Access restricted to System Operator role.
4. Backup TTL — **administrative decision** (not enforced by app).

## Restore procedure

1. Declare incident; assign Privacy Officer + System Operator.
2. Restore DB to isolated environment first when possible.
3. Restore private files to matching paths.
4. Run post-restore reconciliation (see `docs/privacy/post-restore-privacy-reconciliation.md`).
5. `php artisan system:health`
6. Smoke test privacy flows before traffic cutover.

## Deployment blocker

**Restore must be tested at least once before production go-live.** Untested restore = blocker.

See also: `docs/deployment/disaster-recovery-runbook.md`.
