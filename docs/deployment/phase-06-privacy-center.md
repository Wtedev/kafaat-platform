# نشر المرحلة 6 — مركز الخصوصية وطلبات الوصول/التصحيح

## Migration

```bash
php artisan migrate
```

Migration: `2026_07_01_100000_extend_privacy_requests_for_access_correction.php`

- أعمدة: `correction_field_code`, `access_response`, `user_visible_response`
- جدول: `privacy_correction_payloads`

## مسارات جديدة

| Method | Path |
| --- | --- |
| GET | `/portal/privacy` |
| POST | `/portal/privacy/requests/access` |
| POST | `/portal/privacy/requests/correction` |
| POST | `/portal/privacy/requests/{uuid}/cancel` |

## Rate limiting

- `privacy-request` — 5 طلبات/ساعة لكل مستخدم (AppServiceProvider).

## RBAC

- تأكد من seed الصلاحيات: `privacy_requests.correction.execute` لدور `privacy_officer`.

## خارج هذا النشر

- تصدير ZIP، retention cleanup، scheduler — **لا** تُفعَّل في هذه المرحلة.

## التحقق بعد النشر

```bash
php artisan test
php artisan test tests/Feature/PrivacyPhase06/PrivacyCenterTest.php
```
