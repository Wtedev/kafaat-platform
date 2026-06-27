# فجوات الخصوصية والأمن المؤكدة

> كل فجوة مُتحقق منها في الكود. لا تشمل عناصر مخطط لها مستقبلاً فقط.

## حرجة (Critical)

| # | الفجوة | الوضع الحالي | الملف/الدليل | الأثر | المرحلة المقترحة | قرار إداري؟ |
|---|--------|--------------|--------------|-------|------------------|-------------|
| C1 | ملفات CV والصور على قرص `public` | `PortalCompetencyController` يستخدم `Storage::disk('public')`؛ URLs عبر `/storage` | `app/Http/Controllers/Portal/PortalCompetencyController.php`, `config/filesystems.php` | أي شخص يعرف المسار قد يصل للملف دون authorization | 3 — حماية السيرة | نعم (نموذج تخزين) |
| C2 | لا تشفير حقول شخصية في DB | لا `encrypted` casts على Profile/User | `app/Models/User.php`, `app/Models/Profile.php` | تسريب DB يكشف PII مباشرة | 5 — مركز الخصوصية / 6 — تقوية | نعم |

## عالية (High)

| # | الفجوة | الوضع الحالي | الملف/الدليل | الأثر | المرحلة المقترحة | قرار إداري؟ |
|---|--------|--------------|--------------|-------|------------------|-------------|
| H1 | لا رقم هوية | لا عمود في migrations/models | فحص schema | لا تحقق هوية | 1 — بيانات الحساب | نعم |
| H2 | لا اسم رباعي منظم | `users.name` حقل واحد | `RegisterController`, migration users | صعوبة مطابقة هوية | 1 — بيانات الحساب | نعم |
| H3 | لا إقرار خصوصية عند التسجيل | `RegisterController` لا يتحقق من consent | `app/Http/Controllers/Auth/RegisterController.php` | لا دليل موافقة | 2 — سياسة وإقرارات | نعم |
| H4 | لا إصدارات لسياسة الخصوصية | صفحة ثابتة `public.privacy` | `routes/web.php`, `resources/views/public/privacy.blade.php` | لا ربط موافقة بإصدار | 2 — سياسة وإقرارات | نعم |
| H5 | التحقق العام من الشهادة يعرض الاسم الكامل | `certificate-verify.blade.php` | `resources/views/public/certificate-verify.blade.php` | كشف PII علني | 4 أو 6 | نعم (ما يُعرض) |
| H6 | لا audit log لعمليات الموظفين | `user_activity_logs` للمستفيد فقط تقريباً | `app/Services/UserActivityLogger.php` | لا تتبع وصول إداري | 4 — الصلاحيات والسجلات | لا |
| H7 | لا حذف ذاتي للحساب | لا routes/controllers | فحص routes | عدم تمكين GDPR-like rights | 5 — مركز الخصوصية | نعم |

## متوسطة (Medium)

| # | الفجوة | الوضع الحالي | الملف/الدليل | الأثر | المرحلة المقترحة | قرار إداري؟ |
|---|--------|--------------|--------------|-------|------------------|-------------|
| M1 | تاريخ الميلاد موجود في DB غير مستخدم في البوابة | `profiles.birth_date` nullable | migration profiles | بيانات خاملة / عدم اتساق | 1 — بيانات الحساب | لا |
| M2 | الجوال ليس unique | `users.phone` nullable بدون unique | migration | ازدواجية محتملة | 1 | لا |
| M3 | لا retention policy | لا jobs/hard deletes مجدولة | — | تراكم sessions/logs | 5 | نعم |
| M4 | exports قد تحتوي PII زائدة | Filament Excel exports | Resources متعددة | تسريب عبر export | 4 | لا |
| M5 | `SESSION_ENCRYPT=false` في `.env.example` | مثال بيئة | `.env.example` | session payload readable | 6 — تقوية | لا |
| M6 | `APP_DEBUG=true` في `.env.example` | مثال تطوير | `.env.example` | تسريب stack traces إن نُسخ للإنتاج | 6 | نعم |
| M7 | لا مركز خصوصية | لا UI/API | — | لا تصدير/حذف ذاتي | 5 | نعم |
| M8 | لا تصدير بيانات كامل للمستفيد | — | — | حق الوصول غير مُمكّن | 5 | نعم |
| M9 | Spatie activity log غير مستخدم بشكل شامل | package موجود | composer.json | فجوة audit | 4 | لا |

## منخفضة (Low)

| # | الفجوة | الوضع الحالي | الملف/الدليل | الأثر | المرحلة المقترحة | قرار إداري؟ |
|---|--------|--------------|--------------|-------|------------------|-------------|
| L1 | لا CI workflows | لا `.github/workflows` | — | regressions | 6 | لا |
| L2 | ExampleTest يفشل بدون migrations كاملة على `/` | `tests/Feature/ExampleTest.php` | HomeController يقرأ `news` | ضوضاء في CI | 6 | لا |
| L3 | `phone` في users وليس profiles | split storage | User/Profile models | تعقيد نموذج بيانات | 1 | لا |
| L4 | لا security headers middleware مخصص | bootstrap/app.php | — | hardening | 6 | لا |
| L5 | `competency_levels` legacy JSON | profiles | Profile model | بيانات legacy | 3 | لا |