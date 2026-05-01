@extends('layouts.public')
@section('title', $news->title)
@section('content')

{{-- Breadcrumb back --}}
<div class="mb-6">
    <a href="{{ route('public.news.index') }}"
       class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity"
       style="color:#253B5B">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        الأخبار والفعاليات
    </a>
</div>

{{-- Article --}}
<div class="max-w-3xl">

    {{-- Category + date --}}
    <div class="flex items-center gap-3 mb-4 flex-row-reverse justify-end">
        @if ($news->category)
        <span class="text-xs font-medium px-3 py-1.5 rounded-xl"
              style="background:#EAF2FA; color:#253B5B">{{ $news->category }}</span>
        @endif
        @if ($news->published_at)
        <span class="text-xs" style="color:#6B7280">{{ $news->published_at->format('Y/m/d') }}</span>
        @endif
    </div>

    {{-- Title --}}
    <h1 class="text-3xl font-bold leading-snug mb-5 text-right" style="color:#111827">{{ $news->title }}</h1>

    {{-- Excerpt --}}
    @if ($news->excerpt)
    <p class="text-lg font-medium leading-relaxed mb-6 text-right" style="color:#6B7280">{{ $news->excerpt }}</p>
    @endif

    {{-- Featured image --}}
    @if ($news->image)
    <div class="rounded-2xl overflow-hidden mb-8">
        <img src="{{ $news->image }}" alt="{{ $news->title }}"
             class="w-full object-cover" style="max-height:420px">
    </div>
    @else
    <div class="rounded-2xl h-56 flex items-center justify-center mb-8"
         style="background: linear-gradient(135deg, #EAF2FA, #DCE8F5)">
        <svg class="w-20 h-20 opacity-25" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#253B5B">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                  d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
    </div>
    @endif

    {{-- Article content --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-right">
        <div class="prose prose-lg max-w-none leading-relaxed whitespace-pre-line text-right"
             style="color:#374151; font-family: 'IBM Plex Sans Arabic', 'Tajawal', sans-serif; direction: rtl">
            {!! nl2br(e($news->content)) !!}
        </div>
    </div>

    {{-- Back link --}}
    <div class="mt-8">
        <a href="{{ route('public.news.index') }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm
                  hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
           style="background:#253B5B">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            العودة إلى الأخبار
        </a>
    </div>

</div>

@endsection
