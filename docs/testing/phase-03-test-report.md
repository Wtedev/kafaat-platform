# تقرير اختبارات المرحلة 3

| البند | القيمة |
|--------|--------|
| الفرع | `feature/privacy-compliance-phase-03-policy-acknowledgements` |
| التاريخ | 2026-06-28 |

## ملفات الاختبار

| الملف | التغطية |
|-------|---------|
| `tests/Unit/Privacy/PrivacyPolicyPublisherTest.php` | نشر، أرشفة، إصدار فعّال واحد، cache |
| `tests/Feature/PrivacyPhase03/RegistrationAcknowledgementTest.php` | checkbox، إقرار، race version، عدم توفر سياسة |
| `tests/Feature/PrivacyPhase03/ExistingUserReacknowledgementTest.php` | reack، middleware، logout path |
| `tests/Feature/PrivacyPhase03/PublicPrivacyPageTest.php` | عرض عام، draft، archived، sanitization |

## تحديثات regression

- `RegistrationBaselineTest` — يزرع سياسة فعّالة + حقول إقرار.
- `RegistrationIdentityTest` — نفس الزرع.

## النتائج

```text
php artisan test
104 passed, 2 failed (260 assertions)
```

**فشل سابق (غير متعلق):**

- `Tests\Feature\ExampleTest` — جدول `news`.
- `Tests\Unit\Filament\TrainingProgramViewPresenterTest`.

**مرحلة 3:** 20 اختباراً جديداً/محدّثاً — ناجح.

## أوامر

```bash
php artisan migrate --pretend
php artisan test --filter=PrivacyPhase03
php artisan test
```
