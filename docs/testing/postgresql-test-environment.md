# PostgreSQL test environment

Production uses PostgreSQL. Default PHPUnit still uses SQLite `:memory:` for speed; run the suite against Postgres when validating schema, JSON, booleans, FKs, and deletes.

## Prerequisites

- PHP extensions: `pdo_pgsql`, `pgsql` (required in `composer.json`)
- A reachable Postgres instance:

```bash
# Option A — Docker (recommended when Docker Desktop is running)
export POSTGRES_PASSWORD=local_dev_only
docker compose up -d postgres

# Option B — local Homebrew / system Postgres
# create databases: kafaat (migrate/seed) and kafaat_testing (PHPUnit)
createdb kafaat
createdb kafaat_testing
```

Copy env placeholders (no secrets in Git):

```bash
cp .env.testing.example .env.testing
# edit DB_USERNAME / DB_PASSWORD / DB_DATABASE
```

## migrate:fresh --seed on Postgres

```bash
# Load vars from .env.testing, or export manually:
export DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
export DB_DATABASE=kafaat DB_USERNAME=kafaat DB_PASSWORD=local_dev_only DB_URL=

php artisan config:clear
php artisan migrate:fresh --seed --force
```

## Run PHPUnit on Postgres

`phpunit.xml` defaults to SQLite. Env vars win when already set (PHPUnit `force` defaults to false):

```bash
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=kafaat_testing
export DB_USERNAME=kafaat
export DB_PASSWORD=local_dev_only
export DB_URL=

composer test:pgsql
# or: php artisan test
```

SQLite fallback (default):

```bash
unset DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_URL
php artisan test
```

## JSON column types

Laravel `$table->json()` creates PostgreSQL type `json`. Migration `2026_07_20_100000_convert_json_columns_to_jsonb_on_pgsql` alters the critical columns listed below to `jsonb`.

**jsonb (converted):** `training_programs.weekdays|session_topics|acceptance_conditions|program_presenters`, `users.notification_settings`, `security_logs.metadata`, `audit_logs.metadata`, `privacy_request_events.metadata`, `data_deletion_plans.plan_snapshot`.

**Still `json` (not in the critical list):** profile CV JSON fields, `privacy_requests.request_details|access_response`, `in_app_notifications.context`, `retention_runs.summary`.

GIN indexes were **not** added — no application `whereJson*` / JSONB operators were found; Eloquent array casts cover current usage.

## CI

`.github/workflows/ci.yml` starts Postgres 16, runs `migrate:fresh --seed`, Pint, `php artisan test` with `DB_CONNECTION=pgsql`, and `npm run build`. See `docs/ci-local-checks.md`.

## Verified locally (2026-07-20)

- `migrate:fresh --seed` on Postgres: **OK**
- Targeted PG tests (`UserFactory` / RBAC sync / auto-accept / operational access / TipTap extras): **54 passed, 0 failed**
- JSONB conversion migration applied on `pgsql` only (no GIN indexes)
- JSON/acceptance/identity critical subset on PG: **51 passed**
- Many Feature HTTP POST tests return **419 CSRF** on both SQLite and PG (parallel middleware work; not a driver issue)
