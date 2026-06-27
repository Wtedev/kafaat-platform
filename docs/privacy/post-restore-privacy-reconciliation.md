# Post-Restore Privacy Reconciliation

Restoring a database backup without reconciliation is a **potential privacy incident**.

## After any restore, re-apply:

1. **Account anonymizations** — users with `account_anonymized_at` must remain anonymized
2. **Completed deletion runs** — do not resurrect deleted export files or OTP rows
3. **Expired export files** — run `php artisan privacy:purge-expired-exports`
4. **Active retention exceptions** — verify `retention_exceptions.status`
5. **Active retention policies** — verify `retention_policies.status` matches production intent

## Recommended runbook

```bash
# 1. Verify scheduler cron
* * * * * php artisan schedule:run

# 2. Check retention health
php artisan privacy:retention-status

# 3. Preview before execute
php artisan privacy:retention-preview

# 4. Apply active policies (after review)
php artisan privacy:apply-retention

# 5. Purge expired exports explicitly if needed
php artisan privacy:purge-expired-exports
```

## Automated reconcile command

A fully automated `privacy:reconcile-after-restore` is **not shipped** because restore environments vary. Follow this runbook until a environment-specific procedure is validated.

## Rollback

If reconciliation misfires, restore from a known-good snapshot and repeat with preview-first workflow.
