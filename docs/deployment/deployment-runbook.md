# Deployment Runbook

1. Confirm administrative decisions (retention, backup TTL, RBAC assignments).
2. Verify secrets in platform (APP_KEY, DB, IDENTITY_LOOKUP_KEY, RESEND_API_KEY).
3. Enable maintenance mode if required.
4. Deploy artifact / pull release tag.
5. `composer install --no-dev --optimize-autoloader`
6. `npm ci && npm run build` (or deploy prebuilt assets)
7. `php artisan migrate --force`
8. `php artisan db:seed --class=RolesAndPermissionsSeeder` (only if permissions changed)
9. `php artisan permission:cache-reset`
10. `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`
11. Restart queue workers: `php artisan queue:restart`
12. Verify cron runs `schedule:run` every minute.
13. `php artisan system:health`
14. `php artisan privacy:retention-status`
15. Execute smoke test checklist on staging.
16. Disable maintenance mode.
17. Monitor failed jobs, security logs, export failures for 24h.

**Never:** `migrate:fresh`, `db:wipe`, `composer update` in production deploy.

**Public media (news images):** Railway’s container disk is ephemeral. Before production traffic that uploads Filament media, attach a volume at `/app/storage/app/public` (or set `PUBLIC_DISK_DRIVER=s3`). Ops steps: `docs/deployment/public-media-storage.md`.

See `docs/deployment/production-readiness-checklist.md`.
