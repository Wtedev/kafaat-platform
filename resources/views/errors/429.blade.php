@extends('errors.layout')
@php
    $code = 429;
    $title = 'طلبات كثيرة';
    $message = 'لقد تجاوزت عدد المحاولات المسموح بها. يرجى الانتظار قليلاً ثم المحاولة مجدداً.';
    $hint = 'انتظر دقيقة واحدة تقريباً قبل إعادة المحاولة.';
    $showReload = true;
    $autoRefreshSeconds = 0;
@endphp
