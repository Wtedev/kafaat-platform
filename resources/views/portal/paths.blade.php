@php
use App\Enums\RegistrationStatus;

$statusColors = [
RegistrationStatus::Pending->value => 'bg-yellow-100 text-yellow-700',
RegistrationStatus::Approved->value => 'bg-green-100 text-green-700',
RegistrationStatus::Rejected->value => 'bg-red-100 text-red-700',
RegistrationStatus::Cancelled->value => 'bg-gray-100 text-gray-600',
RegistrationStatus::Completed->value => 'bg-blue-100 text-blue-700',
];

$statusLabels = [
RegistrationStatus::Pending->value => 'قيد الانتظار',
RegistrationStatus::Approved->value => 'مقبول',
RegistrationStatus::Rejected->value => 'مرفوض',
RegistrationStatus::Cancelled->value => 'ملغي',
RegistrationStatus::Completed->value => 'مكتمل',
];
@endphp

@extends('layouts.portal')
@section('title', 'مساراتي التعليمية')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">مساراتي التعليمية</h1>

@if (session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
    {{ session('error') }}
</div>
@endif

@if ($registrations->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لم تسجّل في أي مسار تعليمي بعد.
</div>
@else
<div class="space-y-4">
    @foreach ($registrations as $reg)
    @php
    $sv = $reg->status->value;
    $pct = (float) ($reg->progress_percentage ?? 0);
    $total = (int) ($reg->total_courses ?? 0);
    $completed = (int) ($reg->completed_courses ?? 0);
    $path = $reg->learningPath;
    $canAccess = $reg->canAccessCourses();
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                {{-- Title --}}
                <h2 class="font-semibold text-gray-900 text-base truncate">
                    {{ optional($path)->title ?? '—' }}
                </h2>

                {{-- Counts + Status badge --}}
                <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
                    @if ($total > 0)
                    <span class="text-xs text-gray-500">
                        {{ $completed }} / {{ $total }} دورات مكتملة
                    </span>
                    @endif
                    @if ($reg->approved_at)
                    <span class="text-xs text-gray-400">
                        قُبل {{ $reg->approved_at->format('Y/m/d') }}
                    </span>
                    @endif
                </div>

                {{-- Progress bar --}}
                <div class="mt-3 flex items-center gap-2">
                    <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-300
                            @if ($sv === RegistrationStatus::Completed->value) bg-blue-500
                            @elseif ($pct >= 75) bg-green-500
                            @else bg-indigo-500 @endif" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500 w-12 text-left">{{ number_format($pct, 0) }}%</span>
                </div>
            </div>

            {{-- Action button --}}
            <div class="flex-shrink-0">
                @if ($canAccess && $path)
                @if ($sv === RegistrationStatus::Completed->value)
                <a href="{{ route('portal.paths.courses', $path) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition">
                    ✓ تم الإكمال &mdash; عرض الدورات
                </a>
                @else
                <a href="{{ route('portal.paths.courses', $path) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
                    عرض الدورات ←
                </a>
                @endif
                @elseif ($sv === RegistrationStatus::Pending->value)
                <span class="inline-flex items-center px-4 py-2 rounded-xl bg-yellow-50 text-yellow-700 text-sm font-medium border border-yellow-200">
                    بانتظار القبول
                </span>
                @elseif ($sv === RegistrationStatus::Rejected->value)
                <span class="inline-flex items-center px-4 py-2 rounded-xl bg-red-50 text-red-600 text-sm font-medium border border-red-200">
                    مرفوض
                </span>
                @elseif ($sv === RegistrationStatus::Cancelled->value)
                <span class="inline-flex items-center px-4 py-2 rounded-xl bg-gray-50 text-gray-500 text-sm font-medium border border-gray-200">
                    ملغي
                </span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

@if ($registrations->hasPages())
<div class="mt-6">
    {{ $registrations->links() }}
</div>
@endif
@endif
@endsection
