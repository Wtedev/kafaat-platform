@php
use App\Enums\RegistrationStatus;

$statusColors = RegistrationStatus::badgeClasses();

$statusLabels = [
RegistrationStatus::Pending->value => 'قيد المراجعة',
RegistrationStatus::Approved->value => 'مقبول',
RegistrationStatus::Rejected->value => 'مرفوض',
RegistrationStatus::Cancelled->value => 'ملغي',
RegistrationStatus::Completed->value => 'مكتمل',
];
@endphp

@extends('layouts.portal')
@section('title', 'مساراتي')
@section('content')
<h1 class="mb-6 text-2xl font-bold text-gray-900">مساراتي</h1>

@if (session('error'))
<div class="mb-4 {{ config('brand.classes.alert_danger') }} rounded-xl px-4 py-3 text-sm">
    {{ session('error') }}
</div>
@endif

@if ($registrations->isEmpty())
<x-portal.empty-state
    title="لا توجد مسارات مسجّلة"
    description="لم تسجّل في أي مسار بعد. يمكنك استكشاف المسارات المتاحة أو البرامج التدريبية والانضمام من الموقع العام."
>
    <a href="{{ route('public.paths.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف المسارات</a>
    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">استكشف البرامج</a>
</x-portal.empty-state>
@else
<div class="space-y-4">
    @foreach ($registrations as $reg)
    @php
    $sv = $reg->status->value;
    $pct = (float) ($reg->progress_percentage ?? 0);
    $total = (int) ($reg->total_programs ?? 0);
    $completed = (int) ($reg->completed_programs ?? 0);
    $path = $reg->learningPath;
    $canAccess = $reg->canAccessPathPrograms();
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
                        {{ $completed }} / {{ $total }} برامج مكتملة
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
                            @if ($sv === RegistrationStatus::Completed->value) bg-brand
                            @elseif ($pct >= 75) bg-brand-secondary
                            @else bg-brand-accent @endif" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500 w-12 text-left">{{ number_format($pct, 0) }}%</span>
                </div>
            </div>

            {{-- Action button --}}
            <div class="flex-shrink-0">
                @if ($canAccess && $path)
                @if ($sv === RegistrationStatus::Completed->value)
                <a href="{{ route('portal.paths.show', $path) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-95 transition">
                    ✓ تم الإكمال — عرض البرامج
                </a>
                @else
                <a href="{{ route('portal.paths.show', $path) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-95 transition">
                    عرض البرامج ←
                </a>
                @endif
                @elseif ($sv === RegistrationStatus::Pending->value)
                <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium border {{ config('brand.classes.badge_accent') }}">
                    بانتظار القبول
                </span>
                @elseif ($sv === RegistrationStatus::Rejected->value)
                <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium border {{ config('brand.classes.badge_danger') }}">
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
