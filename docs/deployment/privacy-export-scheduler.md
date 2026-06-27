# Scheduler — حذف ملفات التصدير المنتهية

## الأمر

```bash
php artisan privacy:purge-expired-exports
php artisan privacy:purge-expired-exports --dry-run
php artisan privacy:purge-expired-exports --batch=200
```

## Scheduler

مُجدول في `routes/console.php`:

```php
Schedule::command('privacy:purge-expired-exports')
    ->dailyAt('03:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
```

## Deployment Blocker

إذا لم يعمل `schedule:run` في الإنتاج (cron كل دقيقة)، **لن تُحذف** الملفات المنتهية تلقائياً.

```cron
* * * * * cd /var/www/kafaat && php artisan schedule:run >> /dev/null 2>&1
```

## Idempotent

آمن للتشغيل المتكرر؛ lock يمنع التزامن.
