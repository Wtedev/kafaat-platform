@extends('errors.layout')
@php
    $code = $status ?? 400;
    $title = 'طلب غير صالح';
    $message = 'تعذّر تنفيذ الطلب. يرجى التحقق من الرابط ثم المحاولة مجدداً.';
    $showReload = false;
    $autoRefreshSeconds = 0;
@endphp
