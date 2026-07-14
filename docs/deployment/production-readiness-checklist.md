# Production Readiness Checklist

## Application

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` set via secret manager
- [ ] `APP_URL` uses HTTPS
- [ ] `IDENTITY_LOOKUP_KEY` set
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
- [ ] `QUEUE_CONNECTION` not `sync`
- [ ] `PRIVATE_DOCUMENTS_DISK` configured and not public
- [ ] `php artisan system:health` returns healthy
- [ ] `php artisan privacy:retention-status` reviewed

## Infrastructure

- [ ] Cron: `* * * * * php artisan schedule:run`
- [ ] Queue worker running (export + retention)
- [ ] HTTPS termination verified
- [ ] `TRUSTED_HOSTS` set if using host pinning
- [ ] Trusted proxy CIDRs documented (Railway/load balancer)
- [ ] Private storage volume persistent
- [ ] Public media durable (`/app/storage/app/public` volume **or** `PUBLIC_DISK_DRIVER=s3`) — see `docs/deployment/public-media-storage.md`

## Privacy operations

- [ ] Retention policies reviewed; only approved policies active
- [ ] Backup encrypted and access restricted
- [ ] Restore test completed (see backup runbook)
- [ ] Post-restore reconciliation runbook assigned

## Security

- [ ] Composer/NPM audit reviewed (no unmitigated Critical/High)
- [ ] No Telescope/Debugbar in production
- [ ] Admin MFA decision recorded (if not implemented)
- [ ] Incident response contacts defined by role

## Verification

- [ ] Full test suite green
- [ ] Smoke test checklist completed on staging
- [ ] Monitoring alerts configured
