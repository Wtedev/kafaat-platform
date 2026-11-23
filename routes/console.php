<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('privacy:purge-expired-exports')
    ->dailyAt('03:30')
    ->timezone(config('app.timezone', 'Asia/Riyadh'))
    ->withoutOverlapping();

Schedule::command('staff-ui:purge-expired-beneficiary-exports')
    ->dailyAt('03:45')
    ->timezone(config('app.timezone', 'Asia/Riyadh'))
    ->withoutOverlapping();

Schedule::command('privacy:apply-retention')
    ->dailyAt('04:00')
    ->timezone(config('app.timezone', 'Asia/Riyadh'))
    ->withoutOverlapping();

Schedule::command('error-pages:prune --days=90')
    ->dailyAt('04:30')
    ->timezone(config('app.timezone', 'Asia/Riyadh'))
    ->withoutOverlapping();

Schedule::command('auth:purge-expired-pending-registrations')
    ->hourly()
    ->withoutOverlapping();

// Staff alerts follow the data; this keeps time-based ones (open attendance,
// surveys about to close, programs that ended) current even when nobody is browsing.
Schedule::command('staff-alerts:refresh')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Safety net for automatic certificates: anything a model event missed
// (a failed job, a mark added by hand) is picked up within the hour.
Schedule::command('certificates:auto-issue')
    ->hourly()
    ->withoutOverlapping();
