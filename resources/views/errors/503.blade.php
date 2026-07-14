@extends('errors.layout')
@php
    $code = 503;
    $title = 'المنصة غير متاحة مؤقتاً';
    $message = 'نُجري صيانة قصيرة أو تحديثاً للخدمة، ولذلك الصفحة غير متاحة الآن.';
    $hint = 'يرجى الانتظار نحو دقيقتين ثم إعادة تحميل الصفحة. شكراً لصبركم.';
    $autoRefreshSeconds = 120;
    $showReload = true;
@endphp
