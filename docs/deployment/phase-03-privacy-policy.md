# نشر المرحلة 3 — سياسة الخصوصية والإقرارات

## Migrations

- `2026_06_28_100000_create_privacy_policy_tables.php`
  - `privacy_policy_versions`
  - `privacy_policy_acknowledgements`

## Seeder

```bash
php artisan db:seed --class=PrivacyPolicySeeder
```

ينشئ الإصدار `1.0` (active) إن لم يكن موجوداً.

## ترتيب النشر

1. نسخ احتياطي لقاعدة البيانات.
2. نشر الكود.
3. `php artisan migrate --force`
4. `php artisan db:seed --class=PrivacyPolicySeeder`
5. `php artisan config:cache`
6. التحقق من `/privacy` ومسار التسجيل.
7. smoke test: تسجيل جديد + إقرار + OTP.

## Rollback

1. إعادة الكود للإصدار السابق (قبل middleware الإقرار).
2. `php artisan migrate:rollback --step=1` — يحذف جداول السياسة والإقرارات.
3. **تحذير:** يُفقد سجل الإقرارات؛ التسجيل يعود لسلوك ما قبل المرحلة 3.

## عدم وجود سياسة فعّالة

- التسجيل: رسالة «غير متاح مؤقتاً».
- `/privacy`: صفحة «غير متاحة حالياً».
- Filament: تنبيه للمخولين.

## المخاطر

- نشر إصدار بـ `requires_reacknowledgement=true` يوجّه المستخدمين الحاليين لصفحة الإقرار قبل البوابة.
