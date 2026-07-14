@extends('errors.layout')
@php
    $code = 504;
    $title = 'انتهت مهلة الاستجابة';
    $message = 'استغرق الخادم وقتاً أطول من المعتاد للرد، وقد يكون ذلك بسبب ازدحام مؤقت أو تحديث جارٍ.';
    $hint = 'يرجى الانتظار نحو دقيقتين ثم إعادة تحميل الصفحة.';
    $autoRefreshSeconds = 120;
    $showReload = true;
@endphp
