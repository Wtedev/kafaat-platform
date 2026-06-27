# تقرير اختبار المرحلة 4

## الملفات

- `tests/Feature/PrivacyPhase04/PrivateCvStorageTest.php`
- `tests/Feature/PrivacyPhase04/MigratePublicCvsCommandTest.php`
- `tests/Feature/PrivacyPhase04/CandidatePoolConsentTest.php`
- `tests/Feature/PrivacyBaseline/CvBaselineTest.php` (محدّث)

## السيناريوهات

- تخزين private، رفض MIME، تنزيل بheaders أمنية، صلاحيات موظف
- حذف بكلمة مرور
- ترحيل dry-run / idempotent / missing file
- موافقة: prompted، grant، decline، withdraw، reconsent، أهلية القائمة

## التشغيل

```bash
php artisan test tests/Feature/PrivacyPhase04 tests/Feature/PrivacyBaseline/CvBaselineTest.php
```

## Regression

يُنصح بتشغيل `tests/Feature/PrivacyPhase02` و `PrivacyPhase03` قبل الدمج.
