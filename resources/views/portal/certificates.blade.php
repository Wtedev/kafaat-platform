@extends('layouts.portal')
@section('title', 'شهاداتي')
@section('content')
<h1 class="mb-6 text-2xl font-bold text-gray-900">شهاداتي</h1>

@if ($certificates->isEmpty())
<x-portal.empty-state
    title="لا توجد شهادات بعد"
    description="تُصدر الشهادات تلقائياً عند إكمال برنامج أو مسار أو متطلبات تطوع حسب سياسة المنصة. أكمل تعلّمك أو حدّث ملفك ليتماشى مع المتطلبات."
>
    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">استكشف البرامج</a>
    <a href="{{ route('portal.competency') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">طوّر صفحة الكفاءة</a>
</x-portal.empty-state>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach ($certificates as $cert)
    @php
    $typeLabel = match(class_basename($cert->certificateable_type ?? '')) {
    'TrainingProgram' => 'برنامج تدريبي',
    'LearningPath' => 'مسار تعليمي',
    'VolunteerOpportunity' => 'فرصة تطوعية',
    default => null,
    };
    $typeColor = match(class_basename($cert->certificateable_type ?? '')) {
    'TrainingProgram' => 'bg-emerald-100 text-emerald-700',
    'LearningPath' => 'bg-blue-100 text-blue-700',
    'VolunteerOpportunity' => 'bg-amber-100 text-amber-700',
    default => 'bg-gray-100 text-gray-600',
    };
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col gap-3">
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
                @if ($typeLabel)
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColor }} mb-1">
                    {{ $typeLabel }}
                </span>
                @endif
                <p class="text-sm font-semibold text-gray-800 leading-snug">
                    {{ optional($cert->certificateable)->title ?? 'شهادة' }}
                </p>
            </div>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-700" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
            </span>
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
        <a href="{{ $cert->fileUrl() }}" target="_blank" class="mt-auto inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
            تحميل الشهادة
        </a>
        @else
        <span class="mt-auto inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm text-gray-400 border border-dashed border-gray-200">
            جاري إعداد الملف…
        </span>
        @endif

        <a href="{{ route('certificates.verify', $cert->verification_code) }}" target="_blank" class="text-center text-xs text-gray-400 hover:text-indigo-500 transition">
            رابط التحقق ↗
        </a>
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
