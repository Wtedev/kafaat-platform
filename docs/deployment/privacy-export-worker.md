# Queue Worker — تصدير البيانات الشخصية

## Deployment Blocker

وجود `QUEUE_CONNECTION=database` **لا يكفي**. يجب تشغيل عامل Queue فعلياً:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=600
```

## Supervisor (مثال)

```ini
[program:kafaat-queue]
command=php /var/www/kafaat/artisan queue:work database --sleep=3 --tries=3 --timeout=600
autostart=true
autorestart=true
user=www-data
```

## Railway / Docker

- أضف service منفصل `worker` بنفس image وتشغيل `queue:work`.
- لا تستخدم `dispatchSync()` للتصدير.

## Health

- راقب `failed_jobs` وlogs Job `GeneratePersonalDataExport`.
- Job tags: `privacy-export`, `privacy-request:{id}`.

## Rollback

- إيقاف Worker يوقف التوليد الجديد دون حذف ملفات جاهزة.
- تراجع الكود + `php artisan migrate:rollback` للـ migration الجديدة فقط عند الحاجة.
