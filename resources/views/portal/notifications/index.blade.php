@extends('layouts.portal')
@section('title', 'التنبيهات')

@section('content')
<section class="mb-6 flex flex-wrap items-start justify-between gap-3 text-right">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">التنبيهات</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-600">جميع التنبيهات الموجهة لحسابك. يمكنك تعليمها كمقروءة عند الاطلاع.</p>
    </div>
    <a href="{{ route('portal.notifications.settings') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:text-[#335483]">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        إعدادات التنبيهات
    </a>
</section>

@include('portal.partials.inbox-notifications-panel', ['items' => $items, 'unreadCount' => $unreadCount])
@endsection
