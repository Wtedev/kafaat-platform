<div dir="rtl">

# تدقيق الأخطاء والمشكلات — منصة كفاءات

> تقرير تدقيق (قراءة فقط، بلا أي إصلاح) — التاريخ: **2026-07-20**.
> كل بند موثّق بمسار الملف ورقم السطر حيثما أمكن. التصنيفات: **حرج / وظيفي / أمني / قاعدة بيانات / قابلية صيانة / ميزات ناقصة**.

جدول الأولويات: 🔴 عالٍ · 🟠 متوسط · 🟡 منخفض.

---

## أ) حرج (Critical)

لم يُرصد عطل يوقف النظام بالكامل. أقرب بند لذلك هو العطل الوظيفي رقم (و1) الذي يعطّل مساراً إدارياً أساسياً — انظر أدناه.

---

## ب) وظيفي (Functional)

### 🔴 و1 — استثناء `Array to string conversion` في نموذج البرنامج التدريبي
- **الملف:** `app/Support/TrainingProgramExtrasSupport.php:28` (وأيضاً الشرط `visible()` في `:47-48`).
- **السبب:** داخل `descriptionPreviewFields()` يجري تحويل قيمة الحقل مباشرةً: `(string) ($get('description') ?? '')`. عندما تكون النبذة محتوى TipTap **مصفوفة** (حالة نموذج Livewire)، يفشل التحويل من `array` إلى `string`.
- **الأثر:** إنشاء/تعديل برنامج تدريبي بنبذة غنية يُطلق `ViewException` (يظهر في `vendor/filament/schemas/.../section.blade.php`).
- **الدليل الاختباري:** فشل `Tests\Feature\TrainingProgramCreationFlowTest` («admin can create…») باستثناء `Array to string conversion` — من أصل 69 فشلاً، هذان الفشلان حقيقيان (البقية أدوار محذوفة).
- **اتجاه المعالجة (غير مطبّق):** تطبيع القيمة قبل التحويل (مثل ترميز JSON للمصفوفة أو استخراج النص عبر `RichContentSupport::normalizeForStorage`) بدل الـ cast المباشر.

### 🟠 و2 — ازدواج تفضيلات الإشعارات
- **الملف:** `app/Models/User.php:67-68,91-93` (`notify_email` + `notification_settings`).
- **الأثر:** مصدران للحقيقة لتفضيلات الإشعار (منطقي بسيط + JSON فئوي) قد يتعارضان أو يُقرآن بشكل غير متسق.

---

## ج) أمني (Security)

### 🟠 س1 — مفتاح Resend فعلي في `.env` بشجرة العمل
- **الملف:** `.env:4` (`RESEND_API_KEY=re_...`).
- **الحالة:** `.env` **مُستثنى من Git وغير متتبَّع** (تحقق: `git check-ignore .env` ✓)، إذن **ليس في المستودع**. لكن وجود مفتاح حي في شجرة العمل خطر تشغيلي.
- **التوصية:** تدوير المفتاح ونقله إلى مدير أسرار (Railway env)، والإبقاء على `.env.example:75` فارغاً (وهو كذلك).

### 🟡 س2 — قيمة `.env` محلية مشوّهة
- **الملف:** `.env:2` = `MAIL_MAILER=MAIL_MAILER=log` (القيمة الناتجة `MAIL_MAILER=log` بدل `log`).
- **الأثر:** محلي فقط؛ قد يكسر إرسال البريد في التطوير.

### 🟡 س3 — تعليق سطري داخل `.env.example`
- **الملف:** `.env.example:44` (`SESSION_SECURE_COOKIE=false  # اضبطها true…`).
- **الأثر:** التعليقات السطرية بعد القيم قد تُلتقط كجزء من القيمة في بعض المحلّلات. كما يجب ضبط `SESSION_SECURE_COOKIE=true` و`APP_DEBUG=false` و`FORCE_HTTPS=true` في الإنتاج (موثّق في الملف).

### ✅ ملاحظة إيجابية (ليست خطأً)
الوصول للبيانات الحساسة محمي جيداً: كشف رقم الهوية الكامل يتطلّب **تأكيد كلمة المرور + Rate limiting + سجل تدقيق** (`app/Http/Controllers/Admin/BeneficiaryIdentityRevealController.php:29-91`). رقم الهوية **مشفّر** مع HMAC للبحث. حذف المستخدم محمي بحارس على مستوى النموذج (`User.php:238-250`). OTP إلزامي كل جلسة.

---

## د) قاعدة البيانات (Database)

### 🟠 د1 — تباين بيئة القاعدة (dev sqlite / prod pgsql)
- **الدليل:** `config/database.php:20` الافتراضي `sqlite`؛ `.env` المحلي لا يضبط `DB_CONNECTION` فيُستخدم sqlite؛ `.env.example:32` يضبط `pgsql`.
- **الأثر:** خطر عدم تطابق سلوك الأنواع (JSON، التواريخ، `boolean`) بين التطوير والإنتاج.

### 🟠 د2 — أعمدة JSON مخزَّنة نصاً
- **الدليل:** `weekdays`, `session_topics`, `acceptance_conditions`, `program_presenters`, `notification_settings`, `metadata`, `plan_snapshot` (انظر `DATABASE_SCHEMA.md §6`).
- **التوصية:** التأكد أنها `jsonb` في Postgres لأداء واستعلام سليمين.

### 🟡 د3 — أعمدة/جداول متداخلة أو مرشّحة للإهمال
- `profiles.cv_path` مقابل `profiles.current_cv_document_id` (مساران للسيرة؛ `app/Models/Profile.php:654` يشير لإزالة روابط السيرة العامة).
- `users.name` (قديم) مقابل الاسم المنظَّم (`first_name`…`family_name`).
- `error_page_hits` مقابل `error_page_visits` (ازدواج قياس متعمّد).
- ملفات هجرة جداول قديمة محذوفة تبقى في المستودع (`create_path_courses_table`, `create_user_course_progress_table` — حُذفت بـ `2026_05_01_120001_drop_legacy_path_course_tables`).

---

## هـ) قابلية الصيانة (Maintainability)

### 🔴 ه1 — نظام أدوار مزدوج
- **الملف:** `app/Models/User.php:332-495` — كل دوال الدور تفحص `role_type` **و** أدوار Spatie معاً.
- **الأثر:** مصدرا حقيقة للأدوار؛ خطر عدم التزامن، وتعقيد في الاختبار والصيانة. موصى بتوحيدهما (يذكره `README.md:178`).

### 🔴 ه2 — مجموعة اختبارات قديمة (Test debt) تحجب الانحدارات
- **الأثر:** `php artisan test` = **253 ناجح / 69 فاشل / 27 متجاوَز**. **67 من الفشل** سببها `RoleDoesNotExist` لأدوار **محذوفة** بعد إعادة الهيكلة (`trainee` ×54، `programs_management`/أدوار موظفين قديمة ×13).
- **الملفات (أمثلة):** `tests/Feature/TrainingProgramCreationFlowTest.php:239,281`، `tests/Feature/PrivacyPhase06/*`، `tests/Feature/PrivacyPhase05/AccessControlTest.php`، `tests/Feature/PrivacyPhase04/PrivateCvStorageTest.php`، `tests/Feature/PrivacyBaseline/AdminAuthorizationBaselineTest.php`.
- **الأثر التشغيلي:** لا يمكن الوثوق بنتيجة الاختبارات لرصد الانحدارات (بما فيها العطل و1).

### 🟠 ه3 — انحراف توثيق README
- **الملف:** `README.md:109-112` (Laravel 11 / Tailwind CDN / Supabase) و`:76-78` (مسارات تحتوي دورات متسلسلة أُزيلت). لا يعكس Laravel 13 / Tailwind 4 / نموذج البرامج الحالي.

### 🟡 ه4 — التباس تسمية الجدول/النموذج
- `InboxNotification` يُخزَّن في جدول `in_app_notifications` — اسمان مختلفان لنفس الكيان.

---

## و) ميزات ناقصة أو كود ميت (Missing / Dead)

### 🟠 م1 — متحكمات مركز الخصوصية للمستفيد غير مربوطة بمسارات
- **الملفات:** `app/Http/Controllers/Portal/PortalPrivacyCenterController.php`, `PortalPrivacyAccessRequestController.php`, `PortalPrivacyCorrectionRequestController.php`, `PortalPrivacyExportRequestController.php`, `PortalPrivacyExportDownloadController.php`, `PortalPrivacyRequestCancelController.php`.
- **الدليل:** لا إشارة لأيٍّ منها في `routes/web.php` (بحث في `routes/` = لا نتائج)، رغم وجود توثيق `docs/privacy/privacy-center.md`. المسار الوحيد المتاح للمستفيد هو **طلب حذف الحساب** (`web.php:244`).
- **الأثر:** طلبات الوصول/التصحيح/التصدير الذاتية (self-service) تبدو **غير قابلة للوصول عبر الويب** — إما كود ميت أو انحدار بإزالة المسارات. الخلفية (Services + Filament) موجودة وتعمل للمشرفين.

### 🟡 م2 — صفحة الشروط والأحكام محتوى ثابت
- **الملف:** `routes/web.php:168` (`Route::view('/terms', 'public.terms')`) — لا إدارة محتوى لها.

---

## ملخص الأولويات (أعلى 5)

| # | البند | التصنيف | الأولوية |
| --- | --- | --- | --- |
| 1 | استثناء `Array to string conversion` في نموذج البرنامج (`TrainingProgramExtrasSupport.php:28`) | وظيفي | 🔴 |
| 2 | مجموعة اختبارات قديمة تفشل بـ 67 خطأ أدوار وتحجب الانحدارات | قابلية صيانة | 🔴 |
| 3 | نظام أدوار مزدوج (`role_type` + Spatie) | قابلية صيانة | 🔴 |
| 4 | مركز خصوصية المستفيد بلا مسارات ويب (وصول/تصحيح/تصدير) | ميزات ناقصة | 🟠 |
| 5 | مفتاح Resend حي في `.env` بشجرة العمل (غير مُتتبَّع) | أمني | 🟠 |

</div>
