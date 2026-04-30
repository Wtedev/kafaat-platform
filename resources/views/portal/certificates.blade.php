@extends('layouts.portal')
@section('title', 'شهاداتي')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">شهاداتي</h1>

@if ($certificates->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
    <p class="text-4xl mb-3">🏆</p>
    <p class="text-gray-600 font-medium mb-2">لا توجد شهادات بعد</p>
    <p class="text-sm text-gray-400">ستحصل على شهادة تلقائياً بعد إكمال أي برنامج تدريبي أو مسار تعليمي أو تحقيق ساعات التطوع المطلوبة.</p>
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach ($certificates as $cert)
    @php
    $typeLabel = match(class_basename($cert->certificateable_type ?? '')) {
        'TrainingProgram'      => 'برنامج تدريبي',
        'LearningPath'         => 'مسار تعليمي',
        'VolunteerOpportunity' => 'فرصة تطوعية',
        default                => null,
    };
    $typeColor = match(class_basename($cert->certificateable_type ?? '')) {
        'TrainingProgram'      => 'bg-emerald-100 text-emerald-700',
        'LearningPath'         => 'bg-blue-100 text-blue-700',
        'VolunteerOpportunity' => 'bg-amber-100 text-amber-700',
        default                => 'bg-gray-100 text-gray-600',
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
            جاري إعداد الملف…
        </span>
        @endif

        <a href="{{ route('certificates.verify', $cert->verification_code) }}" target="_blank"
           class="text-center text-xs text-gray-400 hover:text-indigo-500 transition">
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
