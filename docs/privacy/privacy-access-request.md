# طلب الوصول إلى البيانات (Access)

## الفرق عن التصدير

| Access | Export (لاحقاً) |
| --- | --- |
| طلب رسمي لمعرفة **فئات** البيانات المحفوظة | ملف ZIP قابل للتنزيل |
| استجابة منظمة داخل النظام | Queue + رابط تنزيل مؤقت |
| `access_response` JSON + `user_visible_response` | `privacy_export_files` |

## إنشاء الطلب

- `POST /portal/privacy/requests/access`
- CSRF، OTP، rate limit `privacy-request`.
- منع أكثر من طلب Access **نشط** لكل مستخدم.
- لا سبب إلزامي، لا تخزين body كامل.

## الحالات

`submitted` → `under_review` → `approved` / `partially_approved` / `rejected` → `completed` / `cancelled`

## الاستجابة

- يبنيها `PrivacyAccessResponseBuilder` كـ `PrivacyAccessResponseSnapshot`.
- فئات: حساب، ملف، إقرارات، قاعدة مرشحين، تسجيلات، حضور، شهادات، وثائق.
- **لا** تتضمن Audit/Security internals أو بيانات موظفين.

## سجلات

- Activity: `privacy_access_requested`, `privacy_access_completed`
- Audit: `privacy_request.created`, `privacy_access.response_created`
