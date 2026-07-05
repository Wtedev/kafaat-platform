@extends('layouts.portal')
@section('title', 'سياسة الخصوصية')

@section('content')
@php
$meta = $policy
    ? 'الإصدار '.$policy->version.($policy->published_at ? ' · '.ar_date($policy->published_at) : '')
    : null;
@endphp

<x-portal.settings-shell title="سياسة الخصوصية" :subtitle="$meta" max-width="max-w-3xl">
    @if ($policy === null)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-900">
        سياسة الخصوصية غير متاحة حالياً.
    </div>
    @else
    <x-portal.settings-card class="px-5 py-5 sm:px-6 sm:py-6">
        <div class="privacy-policy-content space-y-5 text-sm leading-relaxed text-gray-700">
            {!! $sanitizedContent !!}
        </div>
        <p class="mt-6 border-t border-slate-100 pt-4 text-xs text-gray-400">
            <a href="{{ route('public.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#335483] hover:underline">النسخة العامة</a>
        </p>
    </x-portal.settings-card>
    @endif
</x-portal.settings-shell>
@endsection
