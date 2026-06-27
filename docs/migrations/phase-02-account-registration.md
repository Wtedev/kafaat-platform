# Migration — Phase 02 Account Registration

## Migration

`2026_06_27_100000_add_identity_and_structured_name_to_users_table.php`

### أعمدة `users`

| العمود | Nullable | Index |
|--------|----------|-------|
| first_name, father_name, grandfather_name, family_name | نعم | — |
| identity_type | نعم | composite |
| identity_number_ciphertext | نعم | — |
| identity_number_lookup_hash | نعم | **unique** |
| identity_number_last4 | نعم | composite |
| identity_confirmed_at | نعم | — |
| profile_completed_at | نعم | — |

### Rollback

`php artisan migrate:rollback --step=1` — يحذف الأعمدة فقط؛ ciphertext يُفقد من DB.

## النشر

1. Backup DB.
2. ضبط `IDENTITY_LOOKUP_KEY` في secrets **قبل** فتح التسجيل.
3. Deploy code.
4. `php artisan migrate --force`
5. `php artisan config:cache` (إنتاج)
6. Smoke: تسجيل مستخدم تجريبي + استكمال ملف قديم.

## المخاطر

- نسيان المفتاح → فشل التسجيل/الهوية.
- مستخدمون قدامى بدون بيانات → banner استكمال.
- **لا** backfill للاسم القديم.
