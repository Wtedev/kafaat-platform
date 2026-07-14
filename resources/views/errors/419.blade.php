@extends('errors.layout')
@php
    $code = 419;
    $title = 'انتهت الجلسة';
    $message = 'انتهت صلاحية الجلسة لأسباب أمنية. يرجى تحديث الصفحة والمحاولة مجدداً.';
    $showReload = true;
    $autoRefreshSeconds = 0;
@endphp
