@extends('layouts.portal')
@section('title', 'شهاداتي')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">شهاداتي</h1>

@if ($certificates->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لا توجد شهادات صادرة بعد.
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach ($certificates as $cert)
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col gap-3">
        <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-semibold text-gray-800 leading-snug">
                {{ optional($cert->certificateable)->title ?? 'شهادة' }}
            </p>
            <span class="shrink-0 text-xl">🏆</span>
        </div>

        <div class="text-xs text-gray-500 space-y-1">
            <p>
                <span class="font-medium text-gray-600">رقم الشهادة:</span>
                <span class="font-mono">{{ $cert->certificate_number }}</span>
            </p>
            <p>
                <span class="font-medium text-gray-600">تاريخ الإصدار:</span>
                {{ $cert->issued_at?->format('Y/m/d') ?? '—' }}
            </p>
        </div>

        @if ($cert->fileUrl())
        <a href="{{ $cert->fileUrl() }}" target="_blank" class="mt-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition">
            تحميل الشهادة
        </a>
        @else
        <span class="mt-auto inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm text-gray-400 border border-dashed border-gray-200">
            الملف غير متاح بعد
        </span>
        @endif
    </div>
    @endforeach
</div>

@if ($certificates->hasPages())
<div class="mt-6">
    {{ $certificates->links() }}
</div>
@endif
@endif
@endsection
