# TESTING.md — Kafaat Platform QA Guide

> **Local / development use only.** Never run seeders against a production database.

---

## 1. First-Time Setup

```bash
# 1. Copy and configure application environment
cp .env.example .env
# Edit .env: set DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 2. Copy testing environment (gitignored; also auto-created by composer install / phpunit bootstrap)
cp .env.testing.example .env.testing
# Edit DB_* for PostgreSQL if you will run PG-backed tests

# 3. Install PHP dependencies
composer install

# 4. Generate keys
php artisan key:generate
php artisan key:generate --env=testing --force

# 5. Run migrations (app database)
php artisan migrate

# 6. Seed the database (roles, users, sample content, registrations)
php artisan db:seed

# 7. Build frontend assets (Tailwind 4 + Vite 8 — not CDN)
npm ci
composer sync-fonts   # copy resources/fonts/shamel → public/fonts/shamel for Filament/error pages
npm run build

# 8. Start the development server (or use `composer dev` for app+queue+scheduler+vite)
php artisan serve
```

The app will be available at **http://127.0.0.1:8000**.

### Environment files

| File | Tracked | Purpose |
| --- | --- | --- |
| `.env.example` | Yes | Template for local/staging app config |
| `.env.testing.example` | Yes | Template for `php artisan --env=testing` and documented PG runs |
| `.env` | No | Your local app secrets |
| `.env.testing` | No | Disposable test DB credentials |

PHPUnit bootstraps via `tests/bootstrap.php`, which calls `ensure_local_env_files()` in `tests/support/ensure_local_env_files.php`. That helper **only copies** from `*.example` when the target file is **missing** — it never overwrites an existing `.env` or `.env.testing`.

**Why both files on a fresh clone?** Laravel’s bootstrap still probes `.env` even when `APP_ENV=testing` (PHPUnit sets this in `phpunit.xml`). If **both** are absent, PHP emits `file_get_contents(.env)` warnings that PHPUnit 12 surfaces as `WARN` on every test. Copying **only** `.env.testing` silences most test runs but leaves edge paths (package discovery, some artisan hooks) vulnerable; copying both example templates is the minimal fix with no secrets in Git.

`phpunit.xml` sets safe defaults (SQLite `:memory:`, `APP_KEY`, mail/queue drivers). Override with OS env vars for PostgreSQL — see [docs/testing/postgresql-test-environment.md](docs/testing/postgresql-test-environment.md).

### Shamel fonts (source of truth)

| Path | Role |
| --- | --- |
| `resources/fonts/shamel/` | **Source of truth** — referenced by Vite (`resources/css/app.css`) |
| `public/fonts/shamel/` | Runtime copies for Filament (`public/css/shamel-fonts.css`), error pages, static HTML |
| `public/build/assets/` | Vite-hashed bundles for the public site CSS |

After updating font files, run:

```bash
composer sync-fonts
npm run build
```

---

## 2. Running Tests

### Default (SQLite in-memory)

```bash
php artisan test
# or: composer test
```

### PostgreSQL (matches CI)

```bash
export DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
export DB_DATABASE=kafaat_testing DB_USERNAME=kafaat DB_PASSWORD=local_dev_only
export DB_URL=
composer test:pgsql
```

CI (`.github/workflows/ci.yml`) runs: `composer validate`, Pint, `migrate:fresh --seed --env=testing`, `npm ci && npm run build`, then `php artisan test` on PostgreSQL.

### Local CI parity

See [docs/ci-local-checks.md](docs/ci-local-checks.md).

---

## 3. Test Accounts

| Role        | Email                     | Password   | Dashboard                    |
| ----------- | ------------------------- | ---------- | ---------------------------- |
| Admin       | `admin@example.com`       | `password` | http://127.0.0.1:8000/admin  |
| Staff       | `staff@example.com`       | `password` | http://127.0.0.1:8000/admin  |
| Beneficiary | `beneficiary@example.com` | `password` | http://127.0.0.1:8000/portal |
| Beneficiary | `sara@example.com`        | `password` | http://127.0.0.1:8000/portal |
| Beneficiary | `khalid@example.com`      | `password` | http://127.0.0.1:8000/portal |

> **Note:** `admin@kafaat.test` / `staff@kafaat.test` / `beneficiary@kafaat.test` are identical duplicate accounts — usable interchangeably.

---

## 4. Public Website Registration Flow

### 4.1 Browse content (no login required)

| URL                                  | Description                           |
| ------------------------------------ | ------------------------------------- |
| `http://127.0.0.1:8000/`             | Home page — hero + preview sections   |
| `http://127.0.0.1:8000/paths`        | All published learning paths          |
| `http://127.0.0.1:8000/programs`       | All published training programs       |
| `http://127.0.0.1:8000/volunteering` | All published volunteer opportunities |

### 4.2 Self-register as a new beneficiary

1. Go to **http://127.0.0.1:8000/register**
2. Fill in: Name, Email, Password, Confirm Password
3. You are automatically logged in and redirected to **/portal**
4. Your role is `beneficiary` — you can now register for paths, programs, and opportunities

### 4.3 Register for a path / program / opportunity

1. Browse to any content page (e.g. `/paths`)
2. Click any card → detail page opens
3. If logged in as a beneficiary, a **"سجّل الآن"** button appears
4. Click the button → a POST form is submitted
5. A success flash message appears confirming your registration is **pending**
6. If registration window is closed (programs only) or capacity is full, an error is shown instead

---

## 5. Admin / Staff: Approving Registrations

1. Log in at **http://127.0.0.1:8000/login** with `admin@example.com` / `password`
    - You are redirected to **/admin** automatically
2. In the sidebar, navigate to:
    - **"تسجيلات المسارات"** → approve / reject path registrations
    - **"تسجيلات البرامج"** → approve / reject program registrations
    - **"تسجيلات التطوع"** → approve / reject volunteer registrations

### Approve a registration

1. Open the relevant resource list
2. Click the row to open the record
3. Click the **"الموافقة"** action button
4. The status changes to `approved`; a notification email is queued for the beneficiary

### Reject a registration

1. Open the record
2. Click **"الرفض"**, provide a reason in the dialog
3. Status changes to `rejected`; beneficiary receives rejection email

---

## 6. Updating Progress (Path Courses)

Path progress is tracked per course. After a beneficiary's registration is approved:

1. In the admin panel, navigate to **"مسارات التعلم"** → select a path → view its courses
2. Navigate to **"تقدم المسار"** resource (if shown)
3. Find the beneficiary's record → update the course completion status
4. Progress percentage is computed automatically from approved courses / total courses
5. The beneficiary sees updated progress bars in **http://127.0.0.1:8000/portal/paths**

---

## 7. Triggering Certificate Generation

Certificates are issued automatically when:

- **Training program**: Registration is marked **Completed** AND the beneficiary meets the completion criteria (attendance / score thresholds)
- **Learning path**: Registration is marked **Completed** after all courses are finished

### Steps (Training Program example)

1. Open a program registration record in the admin panel
2. Ensure status is `approved` and the beneficiary has a passing attendance/score
3. Click the **"إتمام البرنامج"** (Mark Completed) action
4. `CertificateService::issue()` is called automatically
5. A certificate record appears in the **"الشهادات"** resource
6. The beneficiary sees the certificate at **http://127.0.0.1:8000/portal/certificates**

---

## 8. Testing Volunteer Hours

The seeder creates the following sample state for `beneficiary@example.com`:

| Opportunity             | Hours | Status   |
| ----------------------- | ----- | -------- |
| تعليم الكبار محو الأمية | 8h    | Approved |
| تعليم الكبار محو الأمية | 7h    | Approved |
| تعليم الكبار محو الأمية | 10h   | Pending  |

(Total expected: 40h, approved so far: 15h → not yet auto-completed)

### Add hours

1. In the admin panel, navigate to **"ساعات التطوع"**
2. Click **"إضافة"** → select user, opportunity, and hour count → save
3. Status is initially **pending**

### Approve hours (and trigger auto-completion)

1. Open a pending volunteer hours record
2. Click **"الموافقة على الساعات"**
3. Hours status → `approved`
4. If `total approved hours ≥ hours_expected` for the opportunity, the volunteer registration is **automatically marked Completed**
5. Verify in **"تسجيلات التطوع"** that the status changed

### Reject hours

1. Open the record → click **"رفض الساعات"**
2. Status changes to `rejected`; hours are not counted toward completion

---

## 9. Quick Smoke Test Checklist

```
[ ] Home page loads without errors
[ ] /paths, /programs, /volunteering list published content
[ ] Guest cannot access /portal (redirected to /login)
[ ] Beneficiary login redirects to /portal
[ ] Admin login redirects to /admin
[ ] Admin cannot access /portal (403)
[ ] Beneficiary cannot access /admin (redirected to /login or 403)
[ ] Self-registration creates a beneficiary user with 'beneficiary' Spatie role
[ ] Path/program/volunteer registration creates a pending record
[ ] Admin can approve → status changes + email queued
[ ] Admin can reject with reason → status changes + email queued
[ ] Volunteer hours can be added and approved
[ ] Completing volunteer hours (total ≥ expected) auto-completes the registration
[ ] Marking a program complete (with eligibility) issues a certificate
[ ] Certificate appears in /portal/certificates
[ ] Portal sidebar shows correct counts on dashboard
[ ] npm run build completes without font resolution warnings
```

---

## 10. Re-Seeding (Fresh Start)

To wipe all data and start fresh:

```bash
php artisan migrate:fresh --seed
```

> This drops and recreates all tables, then re-runs all seeders.

---

## 11. Useful Artisan Commands

```bash
# List all registered routes
php artisan route:list

# Clear all caches
php artisan optimize:clear

# Run a tinker session
php artisan tinker

# Check a user's roles
>>> App\Models\User::where('email','beneficiary@example.com')->first()->getRoleNames()

# Manually assign a role
>>> $u = App\Models\User::where('email','test@example.com')->first();
>>> $u->assignRole('beneficiary');

# Role drift (Spatie vs role_type)
php artisan roles:report-drift
```

---

## 12. Skipped privacy-center tests

Suites under `tests/Feature/PrivacyPhase06/` skip when `portal.privacy` routes are absent. Routes were **intentionally removed** in commit `335c4c7`; beneficiary self-service privacy flows are handled via **Settings → delete account** and staff Filament `PrivacyRequestResource`. See `docs/privacy/privacy-center.md`.
