<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('training:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

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
