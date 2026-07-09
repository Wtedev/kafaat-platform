@extends('layouts.public')
@section('title', $news->title)
@section('content')

{{-- Breadcrumb back --}}
<div class="mb-6">
    <a href="{{ route('public.news.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity" style="color:#335483">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        الأخبار والفعاليات
    </a>
</div>

{{-- Article --}}
<div class="max-w-4xl">

    {{-- Category + date --}}
    <div class="flex items-center gap-3 mb-4 flex-row-reverse justify-end">
        @if ($news->category)
        <x-news-category-badge :category="$news->category" size="md" />
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

    @php
        $primaryImage = $news->primaryImageRecord();
        $galleryImages = $news->galleryImageRecords();
        $hasPrimary = filled($primaryImage?->path) || filled($news->image);
    @endphp

    @if ($hasPrimary)
        <x-news-gallery
            :primary-url="$news->imagePublicUrl()"
            :primary-alt="$news->title"
            :gallery-urls="$galleryImages->map(fn ($image) => $image->publicUrl())->all()"
        />
    @else
    <div class="rounded-2xl h-56 flex items-center justify-center mb-8" style="background: linear-gradient(135deg, #e9eff6, #DCE8F5)">
        <svg class="w-20 h-20 opacity-25" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#335483">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
    </div>
    @endif

    {{-- Article content (نص عادي أو HTML من محرّر لوحة التحكم) --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-right">
        @php
            $body = (string) ($news->content ?? '');
            $isRichHtml = $body !== '' && preg_match('/<[a-z][\s\S]*>/i', $body);
        @endphp
        <div class="news-article-body prose prose-lg max-w-none leading-relaxed text-right font-sans {{ $isRichHtml ? 'prose-headings:text-[#111827] prose-a:text-[#335483] prose-strong:text-[#111827]' : 'whitespace-pre-line' }}" style="color:#374151; direction: rtl">
            @if ($isRichHtml)
                {!! clean($body) !!}
            @else
                {!! nl2br(e($body)) !!}
            @endif
        </div>
    </div>

    {{-- Back link --}}
    <div class="mt-8">
        <a href="{{ route('public.news.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm
                  hover:shadow-md transition-all duration-200 hover:-translate-y-0.5" style="background:#335483">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            العودة إلى الأخبار
        </a>
    </div>

</div>

@endsection
