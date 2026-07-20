<div dir="rtl">

# الميزات الحالية — مكتمل مقابل ناقص

> تقرير تدقيق (قراءة فقط) — التاريخ: **2026-07-20**.
> المصدر: `routes/web.php`، متحكمات `app/Http/Controllers/*`، موارد Filament `app/Filament/Resources/*` (30 مورداً)، `app/Filament/Pages/*`، النماذج والخدمات، و`database/seeders/*`. التصنيف "ناقص" يُذكر فقط عند وجود دليل (طريق مبتور/متحكم فارغ/TODO/غياب واجهة).

مؤشرات الحالة: ✅ مكتمل ومُختبَر · 🟡 مكتمل مع ملاحظة/محدودية · 🔧 قيد التطوير/جزئي.

---

## 1. الموقع العام (Public)

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| الصفحة الرئيسية | ✅ | `HomeController` · `web.php:101` |
| البرامج التدريبية (فهرس/مسار كفاءة/تفاصيل/تسجيل) | ✅ | `PublicTrainingProgramController` · `web.php:137-147` |
| المسارات التعليمية (فهرس/تفاصيل/تسجيل) | ✅ | `PublicLearningPathController` · `web.php:129-133` |
| الفرص التطوعية (فهرس/تفاصيل/تسجيل) | ✅ | `PublicVolunteerOpportunityController` · `web.php:149-153` |
| مسارات الكفاءة (Tracks) | ✅ | `PublicCompetencyTracksController` · `web.php:135` |
| الأخبار (فهرس/تفاصيل) | ✅ | `PublicNewsController` · `web.php:155-158` |
| اللوائح | ✅ | `PublicRegulationController` · `web.php:160` |
| الحوكمة (مجلس/لجان/وثائق/قرارات استثمار) | ✅ | `PublicGovernanceController` · `web.php:162` |
| المركز الإعلامي (صور/ألبومات) | ✅ | `PublicMediaController` · `web.php:164` |
| سياسة الخصوصية (الحالية + الإصدارات) | ✅ | `PublicPrivacyPolicyController` · `web.php:166-167` |
| الشروط والأحكام | 🟡 | عرض ثابت `Route::view('/terms')` · `web.php:168` (محتوى Blade فقط) |
| تذاكر الدعم (إرسال عام) | ✅ | `SupportTicketController@store` · `web.php:103-105` (Throttled) |
| التحقق من الشهادات (عام بلا دخول) | ✅ | `CertificateVerificationController` · `web.php:108-110` |

---

## 2. المصادقة والحساب

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| تسجيل الدخول / الخروج | ✅ | `LoginController`, `LogoutController` |
| إنشاء حساب | ✅ | `RegisterController` + `UserRegistrationService` |
| استعادة كلمة المرور | ✅ | `ForgotPasswordController`, `ResetPasswordController` |
| تحقق البريد بـ OTP رقمي | ✅ | `EmailVerification*Controller` + `EmailVerificationCodeService` |
| فرض OTP كل جلسة | ✅ | `EnsureOtpVerified` |
| تفضيلات إشعار البريد (نافذة لمرة واحدة) | ✅ | `NotificationPreferenceController` · `web.php:95` |

---

## 3. بوابة المستفيد (`/portal`)

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| لوحة البوابة | ✅ | `PortalDashboardController` · `web.php:177` |
| الإشعارات (عرض/قراءة/إجراء تسجيل/إعدادات) | ✅ | `PortalInboxController` · `web.php:179-186` |
| المسارات (عرض/تفاصيل/حضور) | ✅ | `PortalPathController`, `PortalPathDetailController`, `PortalAttendance*` · `web.php:187-192` |
| البرامج (عرض/تفاصيل/حضور) | ✅ | `PortalProgramController`, `PortalProgramDetailController` · `web.php:193-198` |
| التطوع | ✅ | `PortalVolunteerController` · `web.php:199` |
| الشهادات | ✅ | `PortalCertificateController` · `web.php:200` |
| الملف الشخصي (عرض/تحديث/إكمال) | ✅ | `PortalProfileController`, `PortalProfileCompleteController` · `web.php:202-205` |
| الإعدادات (حساب/ملف/قانوني/كلمة مرور) | ✅ | `PortalSettingsController`, `PortalPasswordController` · `web.php:207-212` |
| إقرار سياسة الخصوصية | ✅ | `PortalPrivacyPolicyAcknowledgeController` · `web.php:214-219` |
| ملف الكفاءات + تصدير PDF | ✅ | `PortalCompetencyController`, `PortalCompetencyExportController` (mpdf) · `web.php:221-225` |
| موافقة التوظيف على الكفاءات | ✅ | `PortalCompetencyEmploymentConsentController` · `web.php:223` |
| رفع/تنزيل/حذف السيرة الذاتية (قرص خاص) | ✅ | `PortalCvDocumentController` · `web.php:227-229` |
| قاعدة المرشحين (طلب/منح/رفض/إعدادات) | ✅ | `PortalCandidatePool*Controller` · `web.php:231-242` |
| حذف الحساب (طلب) | ✅ | `PortalAccountDeletionController` · `web.php:244-245` |

> **مركز الخصوصية للمستفيد:** توجد متحكمات كاملة (`PortalPrivacyCenterController`, `PortalPrivacyAccessRequestController`, `PortalPrivacyCorrectionRequestController`, `PortalPrivacyExportRequestController`, `PortalPrivacyExportDownloadController`, `PortalPrivacyRequestCancelController`) — لكنها **غير مربوطة بمسارات في `routes/web.php`** ضمن العينة المرصودة. راجع `BUG_AUDIT.md` (ميزة قد تكون غير مُفعّلة عبر الويب).

---

## 4. الحضور والباركود (QR / رمز حي)

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| بوابة الحضور `/gate/{program}` (دخول مسؤول تحضير) | ✅ | `GateAttendanceController` · `web.php:114-127` (Throttled) |
| مسح ووسم الحضور | ✅ | `GateAttendanceController@scan/mark` |
| مسؤولو تحضير مدعوّون برمز | ✅ | جدول `program_attendance_checkers` + `ProgramAttendanceCheckerInviteService` |
| جلسة رمز حضور حي (بوابة المستفيد) | ✅ | `AttendanceLiveSession` + `PortalAttendanceSessionController` + `AttendanceLiveSessionService` |
| تسجيل حضور المستفيد ذاتياً | ✅ | `PortalAttendanceCheckInController` · `web.php:189-195` |

---

## 5. لوحة الإدارة (Filament v5) — 30 مورداً

| المجال | الموارد | الحالة |
| --- | --- | --- |
| التدريب | `TrainingProgramResource`, `LearningPathResource`, `ProgramRegistrationResource`, `PathRegistrationResource`, `CertificateResource` | ✅ (مع علاقات: محرِّرون، حضور، درجات، شهادات) |
| التطوع | `VolunteerOpportunityResource`, `VolunteerRegistrationResource`, `VolunteerHourResource`, `VolunteerTeamResource` | ✅ |
| المحتوى | `NewsResource`, `PartnerResource`, `RegulationResource`, `MediaPhotoResource` | ✅ |
| الحوكمة | `BoardMemberResource`, `GovernanceDocumentResource`, `GovernanceCommitteeResource`, `InvestmentDecisionYearResource`, `GeneralAssemblyMemberResource` | ✅ |
| المستخدمون | `UserResource`, `ProfileResource`, `RoleResource`, `PermissionResource` | ✅ |
| الخصوصية والاحتفاظ | `PrivacyPolicyVersionResource`, `PrivacyRequestResource`, `RetentionPolicyResource`, `RetentionRunResource`, `RetentionExceptionResource` | ✅ |
| قاعدة المرشحين | `CandidatePoolMemberResource`, `CandidatePoolConsentVersionResource` | ✅ |
| الأمن والدعم | `AuditLogResource`, `SecurityLogResource`, `SupportTicketResource` | ✅ |
| **صفحات مخصصة** | `StaffPermissionMatrix`, `SendInAppNotification`, `InAppNotificationCenter`, `StaffProfilePage`, `ErrorPageStatsPage` | ✅ |
| **Widgets** | `PlatformStatsWidget`, `LatestInAppNotificationsWidget` | ✅ |

> **ملاحظة على إنشاء/تعديل البرنامج التدريبي:** يوجد **عطل حقيقي** عند عرض "معاينة النبذة المنشورة" حين تكون النبذة محتوى TipTap (مصفوفة) — استثناء `Array to string conversion`. التفاصيل في `BUG_AUDIT.md`. لذا تُصنّف واجهة إنشاء البرنامج 🔧 **جزئياً** رغم اكتمال بقية الحقول.

---

## 6. الشهادات

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| إصدار الشهادات (idempotent، polymorphic) | ✅ | `CertificateService`, `CertificatePdfService`, نموذج `Certificate` |
| تنزيل الشهادة (محمي بـ OTP) | ✅ | `CertificateDownloadController` · `web.php:81-82` |
| التحقق العام برمز 32 حرفاً | ✅ | `CertificateVerificationController` |
| توليد PDF بـ RTL | ✅ | dompdf/mpdf |

---

## 7. الخصوصية وحوكمة البيانات (منظومة متقدمة)

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| إصدارات سياسة الخصوصية + إقرارات | ✅ | `PrivacyPolicyPublisher`, `PrivacyPolicyService` |
| طلبات الخصوصية (وصول/تصحيح/حذف/تصدير) | ✅ (خلفية) | `PrivacyRequestService` + `PrivacyRequestResource` (تدفق اعتماد كامل) |
| تصدير البيانات الشخصية ZIP + تحقق SHA256 + انتهاء صلاحية | ✅ | `Privacy/Export/*` |
| خطط الحذف + خطوات + 14 handler حذف/إخفاء هوية | ✅ | `Privacy/Deletion/*`, جداول `data_deletion_plan*` |
| محرك سياسات الاحتفاظ (Retention) + معاينة + تنفيذ + استثناءات | ✅ | `Privacy/Retention/*`, موارد Filament |
| قاعدة المرشحين + موافقات إصدارية | ✅ | `CandidatePool/*` |
| سجلات التدقيق والأمن | ✅ | `Audit/*`, `Security/*`, موارد Filament |
| إخفاء البيانات الحساسة في السجلات | ✅ | `SensitiveDataRedactor`, `AuditMetadataRedactor` |

---

## 8. الإشعارات

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| إشعارات داخلية (Inbox) | ✅ | `in_app_notifications` / `InboxNotification` + `Inbox/*` |
| إرسال إشعار جماعي من اللوحة | ✅ | `SendInAppNotification` page + Policy |
| إشعارات بريدية (Resend) + سجل | ✅ | `resend/resend-laravel`, `EmailLogService`, `email_logs` |
| تفضيلات إشعارات متعددة الفئات | 🟡 | نظامان (`notify_email` + `notification_settings` JSON) — تأكد من عدم التعارض |

---

## 9. التشغيل والصحة

| الميزة | الحالة | الدليل |
| --- | --- | --- |
| قياس صفحات الأخطاء (عدّاد + سجل) | ✅ | `error_page_hits`, `error_page_visits`, `ErrorPageStatsPage` |
| صفحات أخطاء عربية مُصمّمة | ✅ | `bootstrap/app.php` (render 4xx/5xx) + `emergency-fallback/` |
| صحة النظام | ✅ | `SystemHealthService`, `/up` |
| التحقق من بيئة الإنتاج | ✅ | `ProductionEnvironmentValidator` |

---

## 10. ملخص المكتمل مقابل الناقص

**مكتمل (الأغلب):** الموقع العام، المصادقة + OTP، بوابة المستفيد، الحضور (QR/رمز حي)، الشهادات وتحققها، لوحة إدارة شاملة (30 مورداً)، منظومة خصوصية/احتفاظ/تدقيق متقدمة، الأخبار/الشركاء/الحوكمة/الإعلام، الدعم.

**نواقص/نقاط تحتاج متابعة:**
1. 🔧 **معاينة نبذة البرنامج** تُطلق استثناءً مع محتوى TipTap (عطل واجهة إدارة فعلي).
2. 🟡 **مركز خصوصية المستفيد** — متحكمات موجودة دون مسارات ويب مرصودة (ميزة قد تكون غير مُفعّلة للمستخدم النهائي).
3. 🟡 **صفحة الشروط والأحكام** محتوى ثابت فقط.
4. 🟡 **ازدواج تفضيلات الإشعارات** بين عمود بسيط وJSON.
5. 🔧 **الاختبارات** — 67 اختباراً يفشل بسبب أدوار محذوفة (لا يعكس ميزة ناقصة لكنه يحجب الانحدارات).

</div>
