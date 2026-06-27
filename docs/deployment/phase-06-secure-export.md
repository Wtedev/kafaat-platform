# نشر المرحلة 6 — التصدير الآمن

## Migration

```bash
php artisan migrate
```

`2026_07_02_100000_extend_privacy_export_files_table.php`

## متغيرات

```env
PRIVACY_EXPORT_TTL_DAYS=7
PRIVATE_DOCUMENTS_DISK=private_documents
QUEUE_CONNECTION=database
```

## مسارات جديدة

- `POST /portal/privacy/requests/export`
- `POST /portal/privacy/exports/{uuid}/download`

## Checklist

- [ ] Queue Worker يعمل ([privacy-export-worker.md](./privacy-export-worker.md))
- [ ] Scheduler يعمل ([privacy-export-scheduler.md](./privacy-export-scheduler.md))
- [ ] `PRIVACY_EXPORT_TTL_DAYS` مضبوط
- [ ] Private disk دائم ومُنسخ احتياطياً
- [ ] HTTPS مفعّل

## Rollback

1. إيقاف Worker.
2. `php artisan migrate:rollback --step=1` (migration التصدير فقط).
3. Deploy نسخة سابقة.

## خارج النطاق

Retention engine العام، حذف audit/security logs.
