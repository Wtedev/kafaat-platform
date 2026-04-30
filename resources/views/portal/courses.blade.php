@php
use App\Enums\ProgressStatus;
use App\Enums\CourseStatus;

$progressColors = [
ProgressStatus::NotStarted->value => 'bg-gray-100 text-gray-500',
ProgressStatus::InProgress->value => 'bg-yellow-100 text-yellow-700',
ProgressStatus::Completed->value => 'bg-green-100 text-green-700',
];

$progressLabels = [
ProgressStatus::NotStarted->value => 'لم يبدأ',
ProgressStatus::InProgress->value => 'قيد التقدم',
ProgressStatus::Completed->value => 'مكتمل',
];
@endphp

@extends('layouts.portal')
@section('title', 'دورات المسار: ' . $learningPath->title)
@section('content')

{{-- Header --}}
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <div>
        <a href="{{ route('portal.paths') }}" class="text-sm text-indigo-600 hover:underline">← مساراتي</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $learningPath->title }}</h1>
        @if ($learningPath->description)
        <p class="text-gray-500 text-sm mt-1">{{ $learningPath->description }}</p>
        @endif
    </div>
    {{-- Overall progress --}}
    <div class="flex flex-col items-end gap-1 min-w-[160px]">
        <div class="text-xs text-gray-500 mb-1">
            {{ $completed }} / {{ $total }} دورات إلزامية مكتملة
        </div>
        <div class="w-40 bg-gray-100 rounded-full h-3 overflow-hidden">
            <div class="h-3 rounded-full transition-all
                @if($pathProgress >= 100) bg-green-500
                @elseif($pathProgress >= 50) bg-indigo-500
                @else bg-yellow-400 @endif" style="width: {{ $pathProgress }}%"></div>
        </div>
        <div class="text-xs font-medium text-gray-700">{{ number_format($pathProgress, 0) }}%</div>
    </div>
</div>

{{-- Path completed flash --}}
@if (session('path_completed'))
<div class="mb-5 bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-4 flex items-start gap-3">
    <span class="text-2xl">🎉</span>
    <p class="text-sm leading-relaxed">{{ session('path_completed') }}</p>
</div>
@endif

@if (session('success'))
<div class="mb-4 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-xl px-4 py-3 text-sm">
    {{ session('success') }}
</div>
@endif

{{-- Courses grid --}}
@if ($courses->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لا توجد دورات منشورة في هذا المسار بعد.
</div>
@else
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($courses as $course)
    @php
    /** @var \App\Models\PathCourse $course */
    $prog = $progressMap->get($course->id);
    $status = $prog?->status ?? \App\Enums\ProgressStatus::NotStarted;
    $sv = $status->value;
    $pct = (float) ($prog?->progress_percentage ?? 0);
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col overflow-hidden hover:shadow-md transition">
        <div class="p-5 flex-1 flex flex-col">
            {{-- Number + required badge --}}
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-gray-400 font-mono">#{{ $loop->iteration }}</span>
                <div class="flex items-center gap-1.5">
                    @if ($course->is_required)
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-600 border border-red-100">إلزامي</span>
                    @else
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-50 text-gray-400 border border-gray-100">اختياري</span>
                    @endif
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $progressColors[$sv] ?? 'bg-gray-100 text-gray-500' }}">
                        {{ $progressLabels[$sv] ?? $sv }}
                    </span>
                </div>
            </div>

            {{-- Title --}}
            <h2 class="font-semibold text-gray-900 text-sm leading-snug mb-1 line-clamp-2">
                {{ $course->title }}
            </h2>

            @if ($course->description)
            <p class="text-xs text-gray-500 line-clamp-2 mb-2">{{ $course->description }}</p>
            @endif

            {{-- Duration --}}
            @if ($course->duration_minutes)
            <p class="text-xs text-gray-400 mb-3">🕐 {{ $course->duration_minutes }} دقيقة</p>
            @endif

            {{-- Progress bar --}}
            <div class="mt-auto">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="h-1.5 rounded-full {{ $sv === 'completed' ? 'bg-green-500' : 'bg-indigo-400' }}" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-[10px] text-gray-400 w-8 text-right">{{ (int) $pct }}%</span>
                </div>

                <a href="{{ route('portal.paths.courses.show', [$learningPath, $course]) }}" class="block w-full text-center py-2 rounded-xl text-sm font-medium transition
                       @if ($sv === 'completed')
                           bg-green-600 text-white hover:bg-green-700
                       @elseif ($sv === 'in_progress')
                           bg-indigo-600 text-white hover:bg-indigo-700
                       @else
                           bg-gray-100 text-gray-700 hover:bg-gray-200
                       @endif">
                    @if ($sv === 'completed') ✓ راجع الدورة
                    @elseif ($sv === 'in_progress') تابع الدورة
                    @else ابدأ الدورة
                    @endif
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
