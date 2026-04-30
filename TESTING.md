# TESTING.md — Kaffaat Platform QA Guide

> **Local / development use only.** Never run seeders against a production database.

---

## 1. First-Time Setup

```bash
# 1. Copy and configure environment
cp .env.example .env
# Edit .env: set DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 2. Install PHP dependencies
composer install

# 3. Generate application key
php artisan key:generate

# 4. Run all migrations
php artisan migrate

# 5. Seed the database (roles, users, sample content, registrations)
php artisan db:seed

# 6. Install frontend assets (optional, CDN Tailwind is used)
npm install && npm run build

# 7. Start the development server
php artisan serve
```

The app will be available at **http://localhost:8000**.

---

## 2. Test Accounts

| Role        | Email                     | Password   | Dashboard                    |
| ----------- | ------------------------- | ---------- | ---------------------------- |
| Admin       | `admin@example.com`       | `password` | http://localhost:8000/admin  |
| Staff       | `staff@example.com`       | `password` | http://localhost:8000/admin  |
| Beneficiary | `beneficiary@example.com` | `password` | http://localhost:8000/portal |
| Beneficiary | `sara@example.com`        | `password` | http://localhost:8000/portal |
| Beneficiary | `khalid@example.com`      | `password` | http://localhost:8000/portal |

> **Note:** `admin@kafaat.test` / `staff@kafaat.test` / `beneficiary@kafaat.test` are identical duplicate accounts — usable interchangeably.

---

## 3. Public Website Registration Flow

### 3.1 Browse content (no login required)

| URL                                  | Description                           |
| ------------------------------------ | ------------------------------------- |
| `http://localhost:8000/`             | Home page — hero + preview sections   |
| `http://localhost:8000/paths`        | All published learning paths          |
| `http://localhost:8000/programs`     | All published training programs       |
| `http://localhost:8000/volunteering` | All published volunteer opportunities |

### 3.2 Self-register as a new beneficiary

1. Go to **http://localhost:8000/register**
2. Fill in: Name, Email, Password, Confirm Password
3. You are automatically logged in and redirected to **/portal**
4. Your role is `beneficiary` — you can now register for paths, programs, and opportunities

### 3.3 Register for a path / program / opportunity

1. Browse to any content page (e.g. `/paths`)
2. Click any card → detail page opens
3. If logged in as a beneficiary, a **"سجّل الآن"** button appears
4. Click the button → a POST form is submitted
5. A success flash message appears confirming your registration is **pending**
6. If registration window is closed (programs only) or capacity is full, an error is shown instead

---

## 4. Admin / Staff: Approving Registrations

1. Log in at **http://localhost:8000/login** with `admin@example.com` / `password`
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

## 5. Updating Progress (Path Courses)

Path progress is tracked per course. After a beneficiary's registration is approved:

1. In the admin panel, navigate to **"مسارات التعلم"** → select a path → view its courses
2. Navigate to **"تقدم المسار"** resource (if shown)
3. Find the beneficiary's record → update the course completion status
4. Progress percentage is computed automatically from approved courses / total courses
5. The beneficiary sees updated progress bars in **http://localhost:8000/portal/paths**

---

## 6. Triggering Certificate Generation

Certificates are issued automatically when:

- **Training program**: Registration is marked **Completed** AND the beneficiary meets the completion criteria (attendance / score thresholds)
- **Learning path**: Registration is marked **Completed** after all courses are finished

### Steps (Training Program example)

1. Open a program registration record in the admin panel
2. Ensure status is `approved` and the beneficiary has a passing attendance/score
3. Click the **"إتمام البرنامج"** (Mark Completed) action
4. `CertificateService::issue()` is called automatically
5. A certificate record appears in the **"الشهادات"** resource
6. The beneficiary sees the certificate at **http://localhost:8000/portal/certificates**

---

## 7. Testing Volunteer Hours

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

## 8. Quick Smoke Test Checklist

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
```

---

## 9. Re-Seeding (Fresh Start)

To wipe all data and start fresh:

```bash
php artisan migrate:fresh --seed
```

> This drops and recreates all tables, then re-runs all seeders.

---

## 10. Useful Artisan Commands

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
```
