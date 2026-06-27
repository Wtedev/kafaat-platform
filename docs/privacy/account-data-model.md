# نموذج بيانات الحساب — المرحلة 2

## قرار التخزين

| البيان | الجدول | السبب |
|--------|--------|-------|
| الاسم الرباعي | `users` | الشهادات وFilament وauth تعتمد على `User` |
| الهوية المشفرة | `users` | مرتبطة بالحساب وليس ملف CV |
| تاريخ الميلاد | `profiles.birth_date` | موجود مسبقاً — لم يُنشأ عمود جديد |
| الجوال | `users.phone` | موجود مسبقاً — للتواصل فقط |

## حقل `name` (توافق)

- يبقى في `users` كحقل **compatibility** مؤقت.
- يُحدَّث تلقائياً من `PersonNameService::syncCompatibilityName()` داخل Services — **ليس من الواجهة**.
- المصدر الرسمي للاسم: `first_name`, `father_name`, `grandfather_name`, `family_name`.
- API مركزي: `User::fullName()` و `User::certificateName()`.

## المستخدمون الحاليون

- الحقول الجديدة **nullable**.
- لا backfill آلية للاسم أو الهوية.
- صفحة `/portal/profile/complete` لاستكمال البيانات.
- Banner في البوابة عند `!hasCompletedRequiredIdentityData()`.
- لا منع كامل للمنصة — لا redirect loop.

## تاريخ الميلاد

تاريخ الميلاد محفوظ كبيان شخصي لأغراض الفرز الإداري اليدوي فقط. **لا يوجد** في هذه المرحلة منطق آلي لأهلية البرامج حسب العمر.

## الجوال

- يُخزَّن بصيغة `+9665XXXXXXXX` عبر `SaudiPhoneService`.
- **لا** unique constraint (قرار إداري مطلوب — راجع التقرير).
- **لا** تحقق SMS ولا `phone_verified_at`.
