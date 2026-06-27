# جرد النظام الحالي — الخصوصية والأمن

> المرحلة 1 — توثيق فقط (2026-06-22). مصدر الحقيقة: الكود الفعلي في الفرع `feature/privacy-compliance-phase-01-audit`.

## التقنية

| العنصر | القيمة الفعلية |
|--------|----------------|
| PHP | 8.4.x (`composer.json`: `^8.4`) |
| Laravel | 13.x (مثبت: v13.7.0) |
| Filament | 5.x (مثبت: v5.6.1) |
| PHPUnit | 12.x (مثبت: 12.5.23) — **لا Pest** |
| قاعدة البيانات (إنتاج) | PostgreSQL (`DB_CONNECTION=pgsql`) |
| قاعدة البيانات (اختبار) | SQLite in-memory (`phpunit.xml`) |
| Frontend | Blade + Tailwind CSS 4 + Vite 8 |
| ORM | Eloquent |
| Queue (إنتاج) | `database` |
| Queue (اختبار) | `sync` |
| Session (إنتاج) | `file` |
| Session (اختبار) | `array` |
| Cache (إنتاج) | `file` |
| Cache (اختبار) | `array` |
| Filesystem الافتراضي | `local` (`FILESYSTEM_DISK=local`) |
| ملفات المستخدمين | قرص `public` (`storage/app/public`) |
| البريد | Resend (`MAIL_MAILER=resend`) |
| المصادقة | Guard واحد `web` — session |
| OTP | رمز 6 أرقام عبر البريد في **كل** دخول |
| الأدوار | Spatie Laravel Permission + `users.role_type` |
| Activity log | Spatie Laravel Activitylog (محدود الاستخدام) + `user_activity_logs` |

## أوامر الاختبار والجودة

| الأمر | الحالة |
|-------|--------|
| `php artisan test` | موجود — PHPUnit عبر Artisan |
| `vendor/bin/phpunit` | موجود |
| `vendor/bin/pint` | موجود (Laravel Pint) |
| `npm run build` | Vite build |
| `npm test` / `npm run lint` | **غير موجود** |
| CI workflows (`.github/`) | **غير موجود** |

## المصادقة

| التدفق | Route | Controller | Middleware | Model/Table | View |
|--------|-------|------------|------------|-------------|------|
| التسجيل GET | `register` | `RegisterController@show` | `guest` | — | `auth.register` |
| التسجيل POST | `register` | `RegisterController@store` | `guest`, `throttle:register` | `users`, `profiles` | — |
| الدخول GET/POST | `login` | `LoginController` | `guest`, `throttle:login` | `users`, `sessions` | `auth.login` |
| الخروج POST | `logout` | `LogoutController` | `auth` | — | — |
| OTP GET | `verification.notice` | `EmailVerificationNoticeController` | `auth` | — | `auth.verify-email` |
| OTP POST | `verification.verify` | `EmailVerificationController` | `auth`, `throttle:6,1` | `email_verification_codes` | — |
| إعادة إرسال OTP | `verification.send` | `EmailVerificationResendController` | `auth`, `throttle:3,1` | — | — |
| نسيت كلمة المرور | `password.*` | `ForgotPasswordController`, `ResetPasswordController` | `guest`, `throttle:forgot-password` | `password_reset_tokens` | `auth.*` |

**Middleware مخصصة:** `otp.verified` (`EnsureOtpVerified`), `beneficiary` (`BeneficiaryPortal`), `admin-or-staff` (`EnsureAdminOrStaff`).

**Rate limiting:** login 5/دقيقة، register 3/دقيقة، forgot-password 5/5 دقائق، OTP verify 6/دقيقة، resend 3/دقيقة.

**Session:** `session()->regenerate()` بعد login/register؛ `otp_verified=false` عند كل Login event؛ invalidation عند حساب غير نشط.

## ملف المستفيد — البيانات الشخصية

| الحقل | التخزين | الإدخال | Controller | Validation | من يشاهد | من يعدّل |
|-------|---------|---------|------------|------------|----------|----------|
| الاسم | `users.name` (حقل واحد) | تسجيل + `/portal/profile` | `RegisterController`, `PortalProfileController` | required, max:255 | المالك، الإدارة، exports | المالك، الإدارة |
| البريد | `users.email` (unique) | تسجيل فقط | `RegisterController` | email, unique | المالك، الإدارة | **لا** من البوابة حالياً |
| الجوال | `users.phone` | `/portal/profile` | `PortalProfileController` | nullable, max:30 | المالك، الإدارة | المالك، الإدارة |
| تاريخ الميلاد | `profiles.birth_date` | — (غير في نموذج البوابة الحالي) | — | — | Filament/DB | Filament |
| الجنس | `profiles.gender` | — | — | — | Filament/DB | Filament |
| المدينة | `profiles.city` | `/portal/profile` | `PortalProfileController` | max:100 | المالك، الإدارة | المالك |
| المسمى الوظيفي | `profiles.job_title` | profile + competency | `PortalProfileController`, `PortalCompetencyController` | max:160 | المالك، PDF، الإدارة | المالك |
| الصورة | `profiles.avatar` → `public/avatars/` | `/portal/profile` | `PortalProfileController` | image, max:2048KB | URL عام عبر `/storage` | المالك |
| السيرة (ملف) | `profiles.cv_path` → `public/cv/` | `/portal/competency` | `PortalCompetencyController` | pdf/doc/docx, max:10MB | URL عام محتمل | المالك |
| أقسام السيرة | `profiles.cv_sections` JSON | `/portal/competency` | `PortalCompetencyController` | per-section | PDF export، الإدارة | المالك |
| رقم الهوية | **غير موجود** | — | — | — | — | — |
| الاسم الرباعي | **غير موجود** | — | — | — | — | — |

**Logs:** `UserActivityLogger` يسجل إنشاء الحساب، تحديث الملف، تحديث السيرة، تحميل الشهادات، الدخول/الخروج في `user_activity_logs`.

## البرامج والتسجيل

| التدفق | Route | Service | Table |
|--------|-------|---------|-------|
| تسجيل برنامج | `public.programs.register` | `ProgramRegistrationService` | `program_registrations` |
| تسجيل مسار | `public.paths.register` | `PathRegistrationService` | `path_registrations` |
| تسجيل تطوع | `public.volunteering.register` | Volunteer registration service | `volunteer_registrations` |
| قبول/رفض | Filament | Services + notifications | registrations tables |
| حضور | Portal + Filament + QR | `ProgramAttendanceService` | `program_attendance_sessions`, day records |
| شهادات | `certificates.download` | `CertificateService` | `certificates` |
| تحقق عام | `certificates.verify` | `CertificateVerificationController` | — |

## لوحة الإدارة (Filament `/admin`)

**موارد رئيسية:** User, Profile, TrainingProgram, LearningPath, ProgramRegistration, PathRegistration, Certificate, VolunteerOpportunity, VolunteerRegistration, News, Role, Permission, …

**Policies:** `UserPolicy`, `ProfilePolicy`, `CertificatePolicy`, `ProgramRegistrationPolicy`, …

**Exports:** Maatwebsite Excel + PDF (CV admin: `admin.beneficiaries.cv-pdf`).

## التخزين

| النوع | Disk | المسار | URL |
|-------|------|--------|-----|
| CV | `public` | `cv/*` | `PublicDiskPath::url()` → `/storage/...` |
| Avatar | `public` | `avatars/*` |同上 |
| Staff photo | `public` | staff paths |同上 |
| Certificates PDF | `public` | `certificates/*` | تحميل عبر controller |
| Default app files | `local` | `storage/app/private` | — |

## السجلات

| النوع | الجدول/الموقع | المحتوى |
|-------|---------------|---------|
| نشاط المستفيد | `user_activity_logs` | action, title, detail, user_id |
| البريد | `email_logs` | recipient, subject, template_key, status |
| Laravel | `storage/logs` | أخطاء وتتبع |
| Sessions | `sessions` | payload مشفر جزئياً، user_id, ip |
| Spatie activity | activity_log | استخدام محدود |
| audit موظفين | **لا يوجد جدول مخصص** | — |

## خدمات خارجية

- Resend (بريد)
- Railway proxy (HTTPS headers في `bootstrap/app.php`)
