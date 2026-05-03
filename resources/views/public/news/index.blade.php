@extends('layouts.public')
@section('title', 'الأخبار والفعاليات')
@section('content')

{{-- Page header --}}
<div class="mb-8">
    <h1 class="text-3xl font-bold" style="color:#111827">الأخبار والفعاليات</h1>
    <p class="mt-2 text-sm" style="color:#6B7280">آخر التحديثات والأخبار من منصة كفاءات.</p>
</div>

@php
$categoryColors = [
'إطلاق' => 'style="background:#EAF2FA; color:#253B5B"',
'ورشة عمل' => 'class="bg-green-100 text-green-700"',
'شراكة' => 'class="bg-amber-100 text-amber-700"',
'برامج' => 'class="bg-purple-100 text-purple-700"',
'تقارير' => 'class="bg-sky-100 text-sky-700"',
'فعالية' => 'class="bg-rose-100 text-rose-700"',
'أخرى' => 'style="background:#F3F7FB; color:#6B7280"',
];

$imageBgs = [
'linear-gradient(135deg, #EAF2FA, #DCE8F5)',
'linear-gradient(135deg, #ECFDF5, #D1FAE5)',
'linear-gradient(135deg, #FFF7ED, #FED7AA)',
'linear-gradient(135deg, #F5F3FF, #DDD6FE)',
'linear-gradient(135deg, #FFF1F2, #FFE4E6)',
'linear-gradient(135deg, #F0FDF4, #BBF7D0)',
];
@endphp

@if ($news->isEmpty())
<div class="bg-white rounded-2xl border border-dashed border-gray-200 p-12 text-center" style="color:#6B7280">
    لا توجد أخبار منشورة حالياً.
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach ($news as $index => $item)
    <a href="{{ route('public.news.show', $item->slug) }}" class="group bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-300 hover:-translate-y-1 overflow-hidden block">

        {{-- Image / Gradient placeholder --}}
        @if ($item->image)
        <div class="h-48 overflow-hidden">
            <img src="{{ $item->imagePublicUrl() }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        </div>
        @else
        <div class="h-48 flex items-center justify-center" style="background: {{ $imageBgs[$index % count($imageBgs)] }}">
            <svg class="w-14 h-14 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#253B5B">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
        </div>
        @endif

        <div class="p-6 text-right">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs" style="color:#6B7280">
                    {{ $item->published_at ? $item->published_at->translatedFormat('j F Y') : '' }}
                </span>
                @if ($item->category)
                <span class="text-xs font-medium px-3 py-1 rounded-xl
                             {{ Str::startsWith($categoryColors[$item->category] ?? '', 'class=')
                                 ? ltrim(Str::between($categoryColors[$item->category] ?? '', 'class="', '"'))
                                 : '' }}" {!! Str::startsWith($categoryColors[$item->category] ?? '', 'style=')
                    ? 'style=' . Str::between($categoryColors[$item->category] ?? '', 'style=', '"') . '"'
                    : '' !!}>
                    {{ $item->category }}
                </span>
                @endif
            </div>
            <h3 class="font-bold text-base mb-2 line-clamp-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">{{ $item->title }}</h3>
            @if ($item->excerpt)
            <p class="text-sm line-clamp-3" style="color:#6B7280">{{ $item->excerpt }}</p>
            @endif
            <div class="mt-4 flex items-center justify-end gap-1 text-xs font-semibold" style="color:#253B5B">
                اقرأ المزيد
                <svg class="w-3.5 h-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </div>
        </div>
    </a>
    @endforeach
</div>

@if ($news->hasPages())
<div class="mt-8">{{ $news->links() }}</div>
@endif
@endif

@endsection
