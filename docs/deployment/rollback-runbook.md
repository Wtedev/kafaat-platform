# Rollback Runbook

## Code rollback

1. Redeploy previous release artifact.
2. `php artisan optimize:clear && php artisan config:cache`
3. `php artisan queue:restart`
4. Run smoke tests.

## Migration rollback

- Only when forward migration is reversible and **no privacy deletion/anonymization** occurred since deploy.
- `php artisan migrate:rollback --step=1` per migration with DBA review.
- **Account anonymization and retention deletes are irreversible from the application.**

## Privacy operations

Rollback does **not** restore:

- Anonymized accounts
- Deleted export files
- Retention-run deletions

Database restore from backup requires `docs/privacy/post-restore-privacy-reconciliation.md`.

## Queue / scheduler

- Pause workers during incident.
- Drain or fail-safe pending export jobs before rollback if schema incompatible.
