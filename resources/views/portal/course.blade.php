@php
use App\Enums\ProgressStatus;

$sv = $progress->status->value ?? 'not_started';
$pct = (float) ($progress->progress_percentage ?? 0);
$isCompleted = $sv === ProgressStatus::Completed->value;
$isStarted = $sv !== ProgressStatus::NotStarted->value;
@endphp

@extends('layouts.portal')
@section('title', $pathCourse->title)
@section('content')

{{-- Breadcrumb --}}
<nav class="text-sm mb-5">
    <a href="{{ route('portal.paths') }}" class="text-indigo-600 hover:underline">مساراتي</a>
    <span class="text-gray-400 mx-1">←</span>
    <a href="{{ route('portal.paths.courses', $learningPath) }}" class="text-indigo-600 hover:underline">{{ $learningPath->title }}</a>
    <span class="text-gray-400 mx-1">←</span>
    <span class="text-gray-700">{{ $pathCourse->title }}</span>
</nav>

@if (session('success'))
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm">
    {{ session('success') }}
</div>
@endif

<div class="grid lg:grid-cols-3 gap-6">

    {{-- Main content area --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Course header card --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-start justify-between gap-3 mb-3">
                <h1 class="text-xl font-bold text-gray-900">{{ $pathCourse->title }}</h1>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    @if ($pathCourse->is_required)
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-600 border border-red-100">إلزامي</span>
                    @endif
                    @if ($pathCourse->duration_minutes)
                    <span class="text-xs text-gray-400">🕐 {{ $pathCourse->duration_minutes }} د</span>
                    @endif
                </div>
            </div>

            @if ($pathCourse->description)
            <p class="text-gray-600 text-sm leading-relaxed mb-4">{{ $pathCourse->description }}</p>
            @endif

            {{-- Progress bar --}}
            <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                    <div class="h-2 rounded-full transition-all {{ $isCompleted ? 'bg-green-500' : 'bg-indigo-500' }}" style="width: {{ $pct }}%"></div>
                </div>
                <span class="text-sm font-medium text-gray-700 w-12 text-left">{{ (int) $pct }}%</span>
                @if ($isCompleted)
                <span class="text-green-600 text-sm font-medium">✓ مكتمل</span>
                @endif
            </div>

            @if ($progress->completed_at)
            <p class="text-xs text-gray-400 mt-2">اكتملت في {{ $progress->completed_at->format('Y/m/d H:i') }}</p>
            @endif
        </div>

        {{-- Video embed --}}
        @if ($pathCourse->video_url)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="aspect-video w-full">
                @php
                $vid = $pathCourse->video_url;
                // Convert youtube watch URL to embed
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $vid, $m)) {
                $vid = 'https://www.youtube.com/embed/' . $m[1];
                }
                @endphp
                <iframe src="{{ $vid }}" class="w-full h-full" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" loading="lazy">
                </iframe>
            </div>
        </div>
        @endif

        {{-- Course content --}}
        @if ($pathCourse->content)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 prose prose-sm max-w-none rtl text-gray-800 leading-relaxed">
            {!! $pathCourse->content !!}
        </div>
        @endif

    </div>

    {{-- Sidebar: actions --}}
    <div class="space-y-4">

        {{-- Start button --}}
        @if (! $isStarted)
        <form method="POST" action="{{ route('portal.courses.start', $pathCourse) }}">
            @csrf
            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition shadow">
                ابدأ الدورة
            </button>
        </form>
        @endif

        {{-- Update progress --}}
        @if ($isStarted && ! $isCompleted)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 text-sm mb-3">تحديث التقدم</h3>
            <form method="POST" action="{{ route('portal.courses.progress', $pathCourse) }}" class="space-y-3">
                @csrf
                <div>
                    <label for="progress_percentage" class="text-xs text-gray-500 block mb-1">نسبة الإنجاز</label>
                    <div class="flex items-center gap-2">
                        <input type="range" id="progress_percentage" name="progress_percentage" min="0" max="100" step="1" value="{{ (int) $pct }}" class="flex-1 accent-indigo-600" oninput="this.nextElementSibling.textContent = this.value + '%'">
                        <span class="text-sm font-medium text-gray-700 w-10 text-center">{{ (int) $pct }}%</span>
                    </div>
                </div>
                <div>
                    <label for="score" class="text-xs text-gray-500 block mb-1">الدرجة (اختياري)</label>
                    <input type="number" id="score" name="score" min="0" max="100" step="0.5" value="{{ $progress->score ?? '' }}" placeholder="0 – 100" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
                @error('progress_percentage')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                @error('score')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <button type="submit" class="w-full py-2 rounded-xl bg-gray-800 text-white text-sm font-medium hover:bg-gray-900 transition">
                    حفظ التقدم
                </button>
            </form>
        </div>

        {{-- Complete course --}}
        <form method="POST" action="{{ route('portal.courses.complete', $pathCourse) }}" onsubmit="return confirm('هل تريد تأكيد إنهاء هذه الدورة؟')">
            @csrf
            <button type="submit" class="w-full py-3 rounded-xl bg-green-600 text-white font-semibold hover:bg-green-700 transition shadow">
                ✓ إنهاء الدورة
            </button>
        </form>
        @endif

        @if ($isCompleted)
        <div class="bg-green-50 border border-green-200 rounded-2xl p-5 text-center">
            <div class="text-3xl mb-2">🏆</div>
            <p class="font-semibold text-green-800 text-sm">أحسنت! أكملت هذه الدورة.</p>
            @if ($progress->score !== null)
            <p class="text-green-600 text-xs mt-1">الدرجة: {{ number_format($progress->score, 1) }}</p>
            @endif
        </div>
        @endif

        {{-- Back link --}}
        <a href="{{ route('portal.paths.courses', $learningPath) }}" class="block w-full py-2.5 rounded-xl border border-gray-200 text-center text-sm text-gray-600 hover:bg-gray-50 transition">
            ← العودة إلى قائمة الدورات
        </a>

    </div>
</div>
@endsection
