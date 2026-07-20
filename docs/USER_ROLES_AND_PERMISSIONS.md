<div dir="rtl">

# الأدوار والصلاحيات والمصادقة — منصة كفاءات

> تقرير تدقيق (قراءة فقط) — التاريخ: **2026-07-20**.
> المصدر: `app/Models/User.php`, `app/Services/Rbac/*`, `database/seeders/RolesAndPermissionsSeeder.php`, `app/Http/Middleware/*`, `bootstrap/app.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Policies/*`.

---

## 1. نظرة عامة

يعتمد النظام على **spatie/laravel-permission (v7.4.1)** على الحارس `web`، مع **نظام موازٍ** عبر عمود `users.role_type`. توجد **أربعة أدوار تطبيقية** فقط بعد إعادة الهيكلة (هجرة `2026_06_22_160000_restructure_platform_roles`):

| الدور (Spatie) | ثابت في `RbacCatalog` | التسمية العربية | الوصف |
| --- | --- | --- | --- |
| `admin` | `ROLE_ADMIN` | أدمن | صلاحيات كاملة (كل الصلاحيات). حساب واحد محمي. |
| `staff` | `ROLE_STAFF` | موظف | لا صلاحيات من الدور نفسه — **تُمنح الصلاحيات مباشرةً لكل موظف**. |
| `beneficiary` | `ROLE_BENEFICIARY` | مستفيد | صلاحيات قراءة البوابة فقط. |
| `volunteer` | `ROLE_VOLUNTEER` | فريق تطوعي | نفس صلاحيات المستفيد (قراءة بوابة). |

المصدر: `app/Services/Rbac/RbacCatalog.php:91-99` و`rolePermissionMatrix()` (`:236-252`).

> ⚠️ **ازدواجية نظام الأدوار:** يوجد مصدران للحقيقة: أدوار Spatie **و** عمود `users.role_type`. دوال `User` تفحص كليهما (`isAdmin()` `:332`, `isStaff()` `:351`, `isPortalUser()` `:363`, `canAccessPanel()` `:450`). هذا يعمل لكنه هشّ ويسبب التباساً (انظر `BUG_AUDIT.md`). حتى `README.md:178` يوصي بتوحيدهما.

---

## 2. الصلاحيات (Permissions)

تُعرَّف أسماء الصلاحيات في `RbacCatalog`:
- **صلاحيات المجال** `domainPermissionNames()` (`:22-51`) — ~90 صلاحية: إدارة الأخبار/الإعلام/اللوائح/الحوكمة/البرامج/الشركاء، اعتماد التسجيلات، إصدار الشهادات، إدارة المتطوعين، الإشعارات، سياسة الخصوصية، بيانات المستفيدين والهوية (مقنّعة/كاملة)، قاعدة المرشحين، الصادرات، سجلات النشاط/التدقيق/الأمن، طلبات الخصوصية، سياسات الاحتفاظ.
- **صلاحيات قديمة** `legacyPermissionNames()` (`:64-79`) — أنماط `resource.action` (users/roles/paths/courses/programs/volunteering/registrations/progress/volunteer_hours/certificates/emails/statistics) — ما زالت مُسجَّلة ومستخدمة في بعض المواضع.
- **صلاحيات للأدمن فقط** `adminOnlyPermissionNames()` (`:54-61`): `manage_roles`, `permissions.assign`, `roles.*`, `users.delete` — **مستثناة** من الأدوار العريضة.

**مصفوفة الأدوار → الصلاحيات** (`rolePermissionMatrix()` `:236-252`):
- `admin` → **كل الصلاحيات** (`allPermissionNames()`).
- `staff` → **[] فارغة** (تُمنح الصلاحيات لكل موظف على حِدة).
- `beneficiary` / `volunteer` → قراءة البوابة فقط: `paths.view, courses.view, programs.view, volunteering.view, registrations.view, progress.view, volunteer_hours.view, volunteer_hours.create, certificates.view, certificates.download, view_notifications`.

### صلاحيات الموظفين المباشرة
تُدار عبر `StaffPermissionService` (`app/Services/Rbac/StaffPermissionService.php`):
- `grantAllAssignable()` — يمنح الموظف الجديد كل الصلاحيات القابلة للتعيين.
- `syncAssignablePermissions()` (`:28`) — يزامن صلاحيات الموظف مع مجموعة، مقيّدة بـ `PermissionMatrixCatalog::assignablePermissionNames()` (الأدمن-فقط مستثناة)، مع تسجيل تدقيقي.
- `migrateUsersToFourRoleModel()` (`:60`) — يُرحّل المستخدمين من الأدوار القديمة (`legacyRoleMigrationMap()`: `media_pr`, `training_manager`, `programs_management`, `technical_admin`, `privacy_officer`, `trainee` ... → `staff`/`beneficiary`).
- `enforceSingleAdmin()` (`:144`) — يفرض **أدمن واحداً محمياً** (حساب `ADMIN_EMAIL` أو أقدم admin)، ويحوّل بقية الـ admins إلى `staff`.

واجهة إدارة الصلاحيات: صفحة `app/Filament/Pages/StaffPermissionMatrix.php` + `PermissionMatrixCatalog`.

---

## 3. كيف تعمل المصادقة

### التدفق
1. **إنشاء حساب / دخول** عبر متحكمات `app/Http/Controllers/Auth/*` (مسارات `routes/web.php:54-67`) خلف حارس `guest` و**Throttling** (`throttle:login`, `throttle:register`, `throttle:forgot-password`).
2. **تحقق البريد OTP** — يرسل النظام **رمزاً رقمياً** (لا رابط Laravel الافتراضي) عبر `User::sendEmailVerificationNotification()` (`User.php:172`) و`EmailVerificationCodeService`. المسارات: `verification.notice/verify/send` (`web.php:70-78`) بحدود محاولات.
3. **OTP إلزامي كل جلسة** — الوسيط `EnsureOtpVerified` يفحص `session('otp_verified') === true`، وإلا يعيد التوجيه إلى `verification.notice`. **البوابة معتمدة على الجلسة** لا على `email_verified_at`، فيُطلب الرمز في كل دخول (`EnsureOtpVerified.php:9-29`).
4. **إعادة التوجيه بعد الدخول** (`bootstrap/app.php`): إن لم يُتحقق OTP → `verification.notice`؛ إن كان `admin/staff` → `/admin`؛ غير ذلك → `portal.dashboard`.

### الحسابات المعطّلة/المحذوفة
- `is_active=false` → لا وصول للوحة (`canAccessPanel()` `User.php:456`)، وعند التعطيل تُبطَل الجلسات (`User::booted` `updated` + `AccountDeactivationService`).
- `account_status` في `anonymized`/`deletion_processing` → منع الوصول التشغيلي (`allowsOperationalAccess()` `:213`, `scopeOperational` `:225`).

---

## 4. البوابات (Middleware) وحمايتها

| الوسيط (alias) | الصنف | الوظيفة |
| --- | --- | --- |
| `otp.verified` | `EnsureOtpVerified` | يفرض إدخال OTP في الجلسة |
| `beneficiary` | `BeneficiaryPortal` | يقصر بوابة `/portal` على مستخدمي البوابة |
| `admin-or-staff` | `EnsureAdminOrStaff` | يقصر على admin/staff |
| `privacy.acknowledged` | `EnsureCurrentPrivacyPolicyAcknowledged` | يفرض إقرار سياسة الخصوصية الحالية |
| `gate.attendance` | `EnsureGateAttendanceAccess` | يحمي بوابة الحضور `/gate/*` |
| `EnsureOperationalAccount` | (مباشر) | يمنع الحسابات المعمّاة/قيد الحذف |
| `ApplySecurityHeaders` | (global web) | رؤوس أمن (CSP/HSTS حسب البيئة) |
| `AssignRequestId` | (global web) | معرّف طلب للتتبع/السجلات |
| `RecordErrorPageHit` | (global) | عدّ صفحات الأخطاء |

مجموعة البوابة (`web.php:172`): `['auth','otp.verified','beneficiary','privacy.acknowledged']`.
لوحة Filament (`AdminPanelProvider.php:146-149`): `authMiddleware = [Authenticate, EnsureOtpVerified]`، والوصول عبر `User::canAccessPanel()`.

---

## 5. السياسات (Policies)

26 Policy تحت `app/Policies/` تُغطّي: `User, Profile, TrainingProgram, LearningPath, PathRegistration, ProgramRegistration, VolunteerOpportunity, VolunteerRegistration, VolunteerHour, VolunteerTeam, Certificate, News, MediaPhoto, Regulation, BoardMember, GovernanceDocument, GovernanceCommittee, InvestmentDecisionYear, AuditLog, SecurityLog, InboxNotification, SendInAppNotification, PrivacyPolicyVersion, PrivacyRequest, RetentionPolicy, RetentionRun, RetentionException`.

تعتمد السياسات على `User::hasPermission()` (`User.php:433`) الذي يفوّض إلى `RbacService` (Spatie + قاعدة البيانات). التفويض داخل Filament يمرّ عبر هذه السياسات + `RegistersNavigationByPermission` (إظهار عناصر التنقل حسب الصلاحية).

---

## 6. أنواع المستخدمين عملياً

| النوع | كيف يُميَّز | أين يدخل |
| --- | --- | --- |
| **أدمن** | `role_type='admin'` أو دور `admin` | لوحة `/admin` (كل شيء) + يستطيع البوابة |
| **موظف** | `role_type='staff'` أو دور `staff` + صلاحيات مباشرة | لوحة `/admin` (حسب صلاحياته) + البوابة |
| **مستفيد** | `role_type='beneficiary'` أو دور `beneficiary` | بوابة `/portal` فقط |
| **فريق تطوعي** | `role_type='volunteer'` أو دور `volunteer` | بوابة `/portal` (نفس صلاحيات المستفيد) |
| **قديم trainee** | متوافقية (`isPortalUser()` `:365`) | يُعامَل كمستفيد |
| **زائر** | غير مُصادق | الموقع العام + تحقق الشهادات |
| **مسؤول تحضير (Gate)** | صف في `program_attendance_checkers` (لا حساب `users`) | بوابة `/gate/{program}` فقط عبر رمز دعوة |

> **ملاحظة أمنية:** لا يوجد تسجيل ذاتي للموظفين — كفاءات فقط تنشئهم من اللوحة. الوصول للبيانات الحساسة (رقم الهوية الكامل، تنزيل السيرة) يتطلب **تأكيد كلمة المرور** و**Rate limiting** و**سجل تدقيق** (`BeneficiaryIdentityRevealController.php`).

</div>
