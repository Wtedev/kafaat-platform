@extends('errors.layout')
@php
    $code = $status ?? 500;
    $title = 'حدث خلل مؤقت';
    $message = 'نعتذر، واجهت المنصة مشكلة غير متوقعة أثناء معالجة طلبك.';
    $hint = 'يرجى الانتظار قليلاً ثم إعادة تحميل الصفحة.';
    $requestId = request()->attributes->get('request_id');
    $autoRefreshSeconds = 120;
    $showReload = true;
@endphp
