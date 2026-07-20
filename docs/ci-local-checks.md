# Local CI quality checks

Run the same gates as `.github/workflows/ci.yml` before opening a PR.

A single workflow runs on pull requests (and pushes to main/develop): Composer validate, Pint, Postgres `migrate:fresh --seed`, PHPUnit, and `npm run build`.

## Prerequisites

- PHP **8.4** with extensions: `gd`, `intl`, `pgsql`, `pdo_pgsql`, `zip`
- Composer 2, Node 20+, PostgreSQL 16 (local or Docker)
- A disposable database (e.g. `kafaat_testing`) — never point at production

## Commands

```bash
# 1. Validate and install PHP deps
composer validate --no-check-publish
composer install

# 2. Testing env (disposable CI-style credentials are fine locally)
cp .env.testing.example .env.testing
# Edit DB_USERNAME / DB_PASSWORD (and APP_KEY via key:generate below).
# Optional Docker:
#   export POSTGRES_PASSWORD=local_dev_only
#   docker compose up -d postgres

php artisan key:generate --env=testing --force
php artisan migrate:fresh --seed --env=testing --force

# 3. Style + tests
vendor/bin/pint --test

# PHPUnit defaults to SQLite via phpunit.xml. To match CI Postgres:
export DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
export DB_DATABASE=kafaat_testing DB_USERNAME=kafaat DB_PASSWORD=local_dev_only DB_URL=
php artisan test
# or: composer test:pgsql

# 4. Frontend
npm ci --ignore-scripts
npm run build
```

## Notes

- Do not commit `.env`, `.env.testing`, or real secrets. Use `.env.example` / `.env.testing.example` as templates only.
- CI uses ephemeral Postgres password `ci_postgres_only` — not a production secret.
