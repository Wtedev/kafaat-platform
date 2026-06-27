# Phase 06 — Retention Engine Deployment

## Pre-deploy checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Seed roles if new permissions: `php artisan db:seed --class=RolesAndPermissionsSeeder`
- [ ] Seed draft policies: `php artisan db:seed --class=RetentionPolicySeeder`
- [ ] Preview each policy intended for activation
- [ ] Activate only approved policies
- [ ] Configure cron for `schedule:run`
- [ ] Verify `php artisan privacy:retention-status`

## Post-deploy verification

```bash
php artisan privacy:retention-preview --resource=privacy_export_files
php artisan privacy:retention-status
```

## Rollback

1. Deactivate policies via Filament or set `status=inactive`
2. Roll back migration if necessary (non-destructive; preserves run history)
3. Do **not** run `privacy:apply-retention` until policies reviewed

## Stop line

This unit completes Phase 06 retention. **Final security hardening phase is not included.**
