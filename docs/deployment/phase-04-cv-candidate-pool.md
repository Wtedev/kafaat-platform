# نشر المرحلة 4 — CV + قاعدة المرشحين

1. Backup DB
2. Backup public CV storage
3. ضبط `PRIVATE_DOCUMENTS_DISK` على تخزين دائم
4. `php artisan down`
5. Deploy
6. `php artisan migrate`
7. `php artisan db:seed --class=CandidatePoolConsentSeeder`
8. `php artisan privacy:migrate-public-cvs --dry-run`
9. `php artisan privacy:migrate-public-cvs`
10. smoke tests (رفع / تنزيل / موافقة / قائمة)
11. التحقق من عدم وجود روابط public للـ CV
12. `php artisan up`

## متغيرات البيئة

- `PRIVATE_DOCUMENTS_DISK`
- `CV_MAX_SIZE_KB`
