@extends('errors.layout')
@php
    $code = 502;
    $title = 'الخدمة غير جاهزة حالياً';
    $message = 'تعذّر الوصول إلى المنصة في هذه اللحظة، وغالباً ما يحدث ذلك أثناء التحديث أو إعادة التشغيل.';
    $hint = 'يرجى الانتظار نحو دقيقتين ثم إعادة تحميل الصفحة.';
    $autoRefreshSeconds = 120;
    $showReload = true;
@endphp
