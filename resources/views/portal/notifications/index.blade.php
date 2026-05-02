@extends('layouts.portal')
@section('title', 'التنبيهات')

@section('content')
<section class="mb-6 text-right">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">التنبيهات</h1>
    <p class="mt-2 max-w-2xl text-sm text-gray-600">جميع التنبيهات الموجهة لحسابك. يمكنك تعليمها كمقروءة عند الاطلاع.</p>
</section>

@include('portal.partials.inbox-notifications-panel', ['items' => $items, 'unreadCount' => $unreadCount])
@endsection
