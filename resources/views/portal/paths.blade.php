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
<section class="mb-6 flex flex-wrap items-end justify-between gap-3 text-right">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">مساراتي</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">متابعة مساراتك التعليمية وتقدّمك في برامج كل مسار.</p>
    </div>
    <a href="{{ route('public.paths.index') }}" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
        استكشف المسارات
    </a>
</section>

@if (session('error'))
<div class="mb-4 {{ config('brand.classes.alert_danger') }}">
    {{ session('error') }}
</div>
@endif

@if ($registrations->isEmpty())
<x-portal.empty-state
    title="لا توجد مسارات مسجّلة"
    description="لم تسجّل في أي مسار بعد. يمكنك استكشاف البرامج التدريبية والفرص التطوعية والانضمام من الموقع العام."
>
    <a href="{{ route('public.paths.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف المسارات</a>
    <a href="{{ route('portal.programs') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">برامجي</a>
</x-portal.empty-state>
@else
<div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
    <p>
        <span class="font-semibold tabular-nums text-slate-700">{{ $registrations->total() }}</span>
        {{ $registrations->total() === 1 ? 'مسار مسجّل' : 'مسارات مسجّلة' }}
    </p>
    <a href="{{ route('portal.programs') }}" class="font-semibold transition hover:underline" style="color:#335483">برامجي ←</a>
</div>

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
    <article class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-[#c5d4e4]">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-base font-bold text-gray-900 sm:text-lg">
                    {{ optional($path)->title ?? '—' }}
                </h2>

                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
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

                <div class="mt-3 flex items-center gap-2">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                        <div
                            class="h-2 rounded-full transition-all duration-300
                            @if ($sv === RegistrationStatus::Completed->value) bg-[#335483]
                            @elseif ($pct >= 75) bg-brand-secondary
                            @else bg-brand-accent @endif"
                            style="width: {{ $pct }}%"
                        ></div>
                    </div>
                    <span class="w-12 text-left text-xs text-gray-500 tabular-nums">{{ en_num($pct, 0) }}%</span>
                </div>
            </div>

            <div class="shrink-0">
                @if ($canAccess && $path)
                <a href="{{ route('portal.paths.show', $path) }}" class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95" style="background:#335483">
                    @if ($sv === RegistrationStatus::Completed->value)
                        عرض البرامج
                    @else
                        متابعة المسار
                    @endif
                    <span aria-hidden="true">←</span>
                </a>
                @elseif ($sv === RegistrationStatus::Pending->value)
                <span class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium {{ config('brand.classes.badge_accent') }}">
                    بانتظار القبول
                </span>
                @elseif ($sv === RegistrationStatus::Rejected->value)
                <span class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium {{ config('brand.classes.badge_danger') }}">
                    مرفوض
                </span>
                @elseif ($sv === RegistrationStatus::Cancelled->value)
                <span class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-500">
                    ملغي
                </span>
                @endif
            </div>
        </div>
    </article>
    @endforeach
</div>

@if ($registrations->hasPages())
<div class="mt-6 rounded-2xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
    {{ $registrations->links() }}
</div>
@endif
@endif
@endsection
