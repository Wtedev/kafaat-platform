# ترحيل السير من public إلى private

## الأمر

```bash
php artisan privacy:migrate-public-cvs [--dry-run] [--batch=100] [--user=ID]
```

## خصائص

- Idempotent — يتخطى من لديه `current_cv_document_id`
- checksum بعد النسخ
- يحذف ملف public فقط بعد نجاح DB + checksum
- counters: processed / migrated / skipped / missing / failed

## Cutover

1. Backup DB + public CV storage
2. Maintenance mode
3. Deploy + migrate
4. `--dry-run` ثم الترحيل الفعلي
5. smoke tests
6. migration تنظيف لاحقة لـ `cv_path` (اختياري بعد التحقق)

## Rollback

- استعادة DB + storage من backup — لا إعادة تلقائية للعامة.
