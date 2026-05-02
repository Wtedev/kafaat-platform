<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| الأمر news:publish-scheduled لا يغيّر published_at ولا «ينشر» الخبر للعامة.
| يمرّ على الأخبار التي حلّ وقتها ولم يُرسل تنبيه الوارد بعد، فيُكمِل الإرسال.
| للظهور على الموقع يكفي أن يكون published_at <= الآن في الطلبات — للتنبيهات فقط نفِّذ schedule:run كل دقيقة (أو schedule:work محلياً).
|--------------------------------------------------------------------------
*/
Schedule::command('news:publish-scheduled')->everyMinute();
