@extends('errors.layout')
@php
    $code = 500;
    $title = 'خطأ في الخادم';
    $message = 'حدث خطأ غير متوقع. تم تسجيل المرجع للمتابعة.';
    $requestId = request()->attributes->get('request_id');
@endphp
