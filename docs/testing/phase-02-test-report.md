# تقرير اختبارات — Phase 02

## البيئة

- PHP 8.4 / Laravel 13.7 / PHPUnit 12.5
- `IDENTITY_LOOKUP_KEY` في `phpunit.xml` (اختبار فقط)

## الأمر

```bash
php artisan test
```

## النتائج (2026-06-27)

| | Count |
|---|------|
| Passed | 84 |
| Failed | 2 (سابقة: ExampleTest, TrainingProgramViewPresenterTest) |
| Phase 02 new | 14 tests |

## ملفات جديدة

- `tests/Unit/Identity/IdentityServicesTest.php`
- `tests/Feature/PrivacyPhase02/RegistrationIdentityTest.php`
- `tests/Feature/PrivacyPhase02/ProfileCompletionTest.php`
- `tests/Concerns/GeneratesTestIdentityData.php`
- تحديث `RegistrationBaselineTest`, `ProfileBaselineTest`

## Pretend migration

```bash
php artisan migrate --pretend
```

✅ أعمدة users + unique على lookup_hash
