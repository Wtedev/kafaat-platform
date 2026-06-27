# طلب تصحيح البيانات (Correction)

## المسار

- `POST /portal/privacy/requests/correction`

## حقول Allowlist

| `field_code` | تعديل مباشر في الملف | يحتاج طلب |
| --- | --- | --- |
| `structured_name` | بدون شهادات | مع شهادات |
| `identity_number` | بدون هوية مثبتة | بعد التثبيت |
| `birth_date` | بدون شهادات/هوية | مع شهادات أو هوية |
| `email` | — | دائماً |

## التحقق الحساس

- `identity_number` و`email`: كلمة مرور + `SensitiveAccessVerification`.
- Security log عند فشل التحقق: `privacy_correction.verification_failed`.

## حماية القيم

- الهوية والبريد الجديد: جدول `privacy_correction_payloads` (مشفّر).
- **لا** plaintext في `request_details` أو Audit أو Filament.
- Filament يعرض `value_last4` فقط عند الحاجة.
- بعد التنفيذ: `consumed_at` يُعلَّم.

## التنفيذ

- `PrivacyCorrectionService::apply()` داخل Transaction.
- Audit: `privacy_correction.applied` (metadata: UUID، field code فقط).
- **الشهادات:** لا تُعدَّل تلقائياً؛ رسالة للموظف وللمستخدم.

## الصلاحية

- `privacy_requests.correction.execute` — منفصلة عن `privacy_requests.execute` (حذف).
