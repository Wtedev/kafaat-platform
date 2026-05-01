@extends('layouts.portal')
@section('title', 'التنبيهات')

@section('content')
<section class="mb-6 text-right">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">التنبيهات</h1>
    <p class="mt-2 max-w-2xl text-sm text-gray-600">جميع التنبيهات الموجهة لحسابك. يمكنك تعليمها كمقروءة عند الاطلاع.</p>
</section>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-gray-600">
        غير مقروء: <span class="font-bold tabular-nums" style="color:#253B5B">{{ $unreadCount }}</span>
    </p>
    @if ($unreadCount > 0)
    <form method="POST" action="{{ route('portal.notifications.read-all') }}">
        @csrf
        <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
            تعليم الكل كمقروء
        </button>
    </form>
    @endif
</div>

@if ($items->isEmpty())
<x-portal.empty-state
    title="لا توجد تنبيهات"
    description="عند حدوث نشاط يخص حسابك (تسجيل، شهادة، أخبار، وغيرها) سيظهر هنا."
/>
@else
<ul class="space-y-3">
    @foreach ($items as $n)
    <li class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm @if($n->read_at === null) ring-1 ring-sky-200/60 @endif">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 text-right">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span class="inline-flex rounded-lg px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-600 ring-1 ring-gray-200 bg-gray-50">
                        {{ $n->type->arabicLabel() }}
                    </span>
                    @if ($n->read_at === null)
                    <span class="inline-flex rounded-lg bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-800 ring-1 ring-sky-200">جديد</span>
                    @endif
                </div>
                <h2 class="mt-2 text-base font-bold text-gray-900">{{ $n->title }}</h2>
                @if ($n->message)
                <p class="mt-2 text-sm leading-relaxed text-gray-700 whitespace-pre-wrap">{{ $n->message }}</p>
                @endif
                @if ($n->sender)
                <p class="mt-2 text-xs text-gray-500">من: {{ $n->sender->name }}</p>
                @endif
                <time class="mt-2 block text-xs text-gray-400" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->translatedFormat('j F Y، H:i') }}</time>
            </div>
            @if ($n->read_at === null)
            <form method="POST" action="{{ route('portal.notifications.read', $n) }}" class="shrink-0">
                @csrf
                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">
                    تعليم كمقروء
                </button>
            </form>
            @endif
        </div>
    </li>
    @endforeach
</ul>
@endif
@endsection
