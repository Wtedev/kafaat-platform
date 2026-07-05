@extends('layouts.portal')
@section('title', 'سياسة الخصوصية')

@section('content')
@include('portal.settings.partials.back-link')

<section class="mb-8 text-right">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">سياسة الخصوصية</h1>
    @if ($policy)
    <p class="mt-2 text-sm text-gray-500">
        الإصدار {{ $policy->version }}
        @if ($policy->published_at)
        · نشر: {{ $policy->published_at->translatedFormat('j F Y') }}
        @endif
    </p>
    @endif
</section>

@if ($policy === null)
<div class="max-w-2xl rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
    سياسة الخصوصية غير متاحة حالياً. يُرجى المحاولة لاحقاً.
</div>
@else
<div class="max-w-3xl rounded-3xl border border-slate-200/70 bg-white px-6 py-6 shadow-sm sm:px-8 sm:py-8">
    <div class="privacy-policy-content space-y-6 leading-relaxed text-gray-700">
        {!! $sanitizedContent !!}
    </div>
    <p class="mt-8 border-t border-gray-100 pt-4 text-xs text-gray-400">
        <a href="{{ route('public.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#335483] hover:underline">فتح النسخة العامة في نافذة جديدة</a>
    </p>
</div>
@endif
@endsection
