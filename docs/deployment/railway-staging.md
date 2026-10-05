# Railway Staging Environment

Staging lives in the existing Railway project. Production is never changed by these steps.

| Item | Value |
|------|-------|
| Project | `harmonious-comfort` (`728e29dc-fce4-45dd-958b-c8fd64d731b6`) |
| Environment | `staging` (`aab5de07-b736-4ec8-aba0-a2b15dbeb603`) |
| Git branch | `staff-ui-shell` |
| Staging URL | `https://kafaat-web-staging-staging.up.railway.app` |
| Production URL (unchanged) | `https://kafaat.org.sa` |

## Services

| Service | Role | Source | Domain |
|---------|------|--------|--------|
| `kafaat-web-staging` | HTTP | `Wtedev/kafaat-platform` branch `staff-ui-shell` | `https://kafaat-web-staging-staging.up.railway.app` |
| `kafaat-worker-staging` | Queue | same branch | none |
| `kafaat-scheduler-staging` | Scheduler | same branch | none |
| `Postgres-bCZA` | Database | `ghcr.io/railwayapp-templates/postgres-ssl:18` | none |
| Bucket | Private files | Railway Bucket | none |

Every Railway command below passes `--environment staging`. Do not omit it. The CLI link may still be production.

## Isolation

Confirmed on `kafaat-web-staging`, `kafaat-worker-staging`, and `kafaat-scheduler-staging`:

- `APP_ENV=staging`
- `APP_DEBUG=false`
- `TRUSTED_HOSTS=kafaat-web-staging-staging.up.railway.app`
- `APP_KEY` and `IDENTITY_LOOKUP_KEY` are set and are not the production values. The three app services share the staging identity key.
- Database host is `postgres-bcza.railway.internal`, not the production Postgres host.
- `MAIL_MAILER=log` and `MAIL_LOG_CHANNEL=stderr`. Mail is written to the service logs and is not delivered to any inbox.
- `LOG_LEVEL=debug` so those logged messages are kept.
- `STAFF_UI_MAINTENANCE=true`. Admins bypass the maintenance page. Other staff see it.
- `ADMIN_EMAIL` is the staging super admin, so the next web boot keeps that account as the only admin.

## Code guard

`StaffUiPreviewSeeder` runs only when `APP_ENV` is `local` or `staging`. `tests/Feature/StaffUiPreviewSeederTest.php` proves it throws in production.

`railway/deploy-staging.sh` redeploys the three app services from `staff-ui-shell`. It does not touch production.

## Steps used on 2026-10-04

1. Confirmed the staging database before wiping it. It contained real personal data: one user with a Gmail address and one profile.
2. Pushed `staff-ui-shell` (`32fa0e6`), including the seeder guard.
3. Set the variables above with `railway variable set --skip-deploys` on the three app services only.
4. Connected each app service to `Wtedev/kafaat-platform` branch `staff-ui-shell`:

```bash
railway service source connect \
  --project 728e29dc-fce4-45dd-958b-c8fd64d731b6 \
  --environment staging \
  --service kafaat-web-staging \
  --repo Wtedev/kafaat-platform \
  --branch staff-ui-shell
```

Repeat for `kafaat-worker-staging` and `kafaat-scheduler-staging`.

5. Waited until all three deployments of commit `32fa0e6` were `SUCCESS`. The worker had been stopped; the new deployment started it.
6. On the staging web container only, after checking `APP_ENV=staging` and database host `postgres-bcza.railway.internal`:

```bash
php artisan migrate:fresh --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=StaffUiPreviewSeeder --force
```

7. Created the two staging accounts (admin and staff) in that database. Passwords are not stored in git.
8. Set `LOG_LEVEL=debug` and `MAIL_LOG_CHANNEL=stderr`, then redeployed the web service so login codes are captured in the web logs instead of being dropped.
9. A web boot runs `railway/predeploy.sh`, which includes `RolesAndPermissionsSeeder`. That seeder keeps only `ADMIN_EMAIL` as admin and turns other admins into staff. After that boot, the preview admin was restored to the admin role for review. The next web boot will demote it again.

## Log in

Open `https://kafaat-web-staging-staging.up.railway.app/login`.

After the password, the verification code is not sent to Gmail. It is written to the `kafaat-web-staging` logs. Search the latest deploy log for the message body.

The admin account can open the staff UI while maintenance is on. The staff account sees the maintenance page.

## Verification

Run inside the staging web container:

```bash
bash /app/railway/verify-staging.sh
```

Run from a machine that can reach the public URL:

```bash
STAGING_URL=https://kafaat-web-staging-staging.up.railway.app bash railway/smoke-test-staging.sh
```

Result after the reset: verification finished, system health reported healthy, with warnings for the migration listing (`pending=-1`) and the scheduler (no completed retention run on the fresh database). Smoke test: `/`, `/login`, `/register`, `/up`, and `/privacy` all returned HTTP 200.

## Production

Do not point these commands at production, and do not deploy `staff-ui-shell` to production from this runbook.
