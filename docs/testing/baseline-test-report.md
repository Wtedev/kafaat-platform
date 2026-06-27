# تقرير اختبارات Baseline — المرحلة 1

## البيئة

| العنصر | القيمة |
|--------|--------|
| الفرع | `feature/privacy-compliance-phase-01-audit` |
| PHP | 8.4.10 |
| Laravel | 13.7.0 |
| PHPUnit | 12.5.23 |
| DB (test) | SQLite `:memory:` |
| الأمر | `php artisan test` |

## قبل إضافة Baseline (2026-06-22)

| المقياس | القيمة |
|---------|--------|
| Passed | 31 |
| Failed | 2 |
| Skipped | 0 |
| Duration | ~1.7s |

**Failures سابقة (ليست من Phase 1):**

1. `Tests\Unit\Filament\TrainingProgramViewPresenterTest::test_present_omits_empty_description_section` — يعرض قسم «نبذة عن البرنامج» رغم الوصف الفارغ.
2. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` — `/` يستدعي `news` بدون migrations في الاختبار.

## الاختبارات الجديدة (40)

| الملف | السيناريوهات |
|-------|--------------|
| `tests/Feature/PrivacyBaseline/RegistrationBaselineTest.php` | صفحة التسجيل، إنشاء user+profile+role، hash كلمة المرور، رفض بريد مكرر، validation |
| `tests/Feature/PrivacyBaseline/LoginBaselineTest.php` | login، رفض كلمة خاطئة/حساب معطل، OTP gate، portal بعد otp_verified |
| `tests/Feature/PrivacyBaseline/EmailOtpBaselineTest.php` | hash الرمز، verify صحيح/خاطئ/منتهي، session flag |
| `tests/Feature/PrivacyBaseline/ProfileBaselineTest.php` | عرض/تحديث الملف، عزل المستخدم، validation جوال |
| `tests/Feature/PrivacyBaseline/CvBaselineTest.php` | public disk baseline، mime/size، عزل المستخدم، PDF export |
| `tests/Feature/PrivacyBaseline/ProgramRegistrationBaselineTest.php` | برنامج/مسار/تطوع، no duplicate، staff forbidden |
| `tests/Feature/PrivacyBaseline/CertificateBaselineTest.php` | download owner/other، verify عام |
| `tests/Feature/PrivacyBaseline/AdminAuthorizationBaselineTest.php` | Filament access، issue cert، policies export |

**Helpers:**

- `tests/Concerns/SeedsRbacRoles.php`
- `tests/Concerns/ActsAsOtpVerifiedUser.php`

## بعد إضافة Baseline

| المقياس | القيمة |
|---------|--------|
| Passed | 71 |
| Failed | 2 |
| Skipped | 0 |
| Duration | ~63s (بسبب seed RBAC في كل test class) |

**Failures المتبقية:** نفس الـ 2 السابقة — **لم تُضف بواسطة Phase 1**.

## التغطية

PHPUnit `--coverage` **لم يُشغَّل** — لا إعداد coverage في `phpunit.xml`.

## القيود

- الاختبارات الجديدة تستخدم SQLite in-memory + `RolesAndPermissionsSeeder` — أبطأ من unit tests معزولة.
- اختبار `test_current_cv_storage_uses_public_disk_as_documented_baseline` **يوثق** الوضع الحالي وليس الهدف النهائي.
- لا اختبارات Filament Livewire كاملة في هذه المرحلة.

## أوامر التشغيل

```bash
php artisan test
php artisan test --filter=PrivacyBaseline
vendor/bin/pint --test   # lint اختياري
```
