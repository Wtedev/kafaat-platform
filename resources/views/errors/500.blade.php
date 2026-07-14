@extends('errors.layout')
@php
    $code = 500;
    $title = 'حدث خلل مؤقت';
    $message = 'نعتذر، واجهت المنصة مشكلة غير متوقعة أثناء معالجة طلبك.';
    $hint = 'يرجى الانتظار نحو دقيقتين ثم إعادة تحميل الصفحة. غالباً ما يعود كل شيء للعمل بعده مباشرة.';
    $requestId = request()->attributes->get('request_id');
    $autoRefreshSeconds = 120;
    $showReload = true;
@endphp
