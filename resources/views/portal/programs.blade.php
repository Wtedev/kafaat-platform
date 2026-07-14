@php
use App\Enums\ProgramDeliveryMode;
use App\Enums\RegistrationStatus;
use App\Support\TrainingProgramExtrasSupport;

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
@section('title', 'البرامج واللقاءات')

@push('styles')
<style>
    .portal-program-card {
        border-radius: 1rem;
        border: 1px solid rgb(243 244 246);
        background: #fff;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .portal-program-card:hover {
        border-color: #c5d4e4;
        box-shadow: 0 8px 24px -16px rgba(51, 84, 131, 0.28);
    }

    .portal-program-metric {
        border-radius: 0.75rem;
        border: 1px solid rgb(243 244 246);
        background: #f9fafb;
        padding: 0.75rem 0.875rem;
        min-width: 0;
    }

    .portal-program-metric__label {
        color: #6b7280;
        font-size: 0.6875rem;
        font-weight: 600;
    }

    .portal-program-metric__value {
        margin-top: 0.35rem;
        color: #111827;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.35;
        word-break: break-word;
    }

    .portal-program-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }
</style>
@endpush

@section('content')
<div>
    <section class="mb-6 flex flex-wrap items-end justify-between gap-3 text-right">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">البرامج واللقاءات</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-600">
                متابعة تسجيلاتك، مواعيد البرامج، التحضير، الحضور، ومجموعات الواتساب والشهادات في مكان واحد.
            </p>
        </div>
        <a
            href="{{ route('public.programs.index') }}"
            class="inline-flex shrink-0 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
            style="background:#335483"
        >
            استكشف البرامج
        </a>
    </section>

@if ($registrations->isEmpty())
    <x-portal.empty-state
        title="لا توجد برامج مسجّلة"
        description="لم تسجّل في أي برنامج تدريبي بعد. تصفّح البرامج المنشورة وسجّل عند توفر مقعد."
    >
        <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف البرامج</a>
    </x-portal.empty-state>
@else
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <p>
            <span class="font-semibold text-slate-700 tabular-nums">{{ $registrations->total() }}</span>
            {{ $registrations->total() === 1 ? 'برنامج مسجّل' : 'برامج مسجّلة' }}
        </p>
    </div>

    <div class="space-y-4">
        @foreach ($registrations as $reg)
        @php
            $sv = $reg->status->value;
            $program = $reg->trainingProgram;
            $isAccepted = in_array($sv, [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ], true);
            $whatsappUrl = $isAccepted && $program
                ? TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, auth()->user())
                : null;
            $timingLabel = $program?->portalTimingLabel();
            $programShowUrl = ($program && filled($program->slug))
                ? route('public.programs.show', $program)
                : null;
            $checkInUrl = $program ? route('portal.programs.show', $program) : null;
            $isInPerson = $program?->delivery_mode === ProgramDeliveryMode::InPerson;

            $showElig = in_array($reg->status->value, [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ], true);
            $attOk = $reg->attendance_percentage !== null && (float) $reg->attendance_percentage >= 80;
            $scoreOk = $reg->score === null || (float) $reg->score >= 60;

            $attendanceDisplay = $reg->attendance_percentage !== null
                ? number_format((float) $reg->attendance_percentage, 1).'%'
                : '—';
            $scoreDisplay = $reg->score !== null
                ? number_format((float) $reg->score, 1)
                : '—';

            if ($showElig && $reg->attendance_percentage !== null) {
                $eligDisplay = ($attOk && $scoreOk) ? 'مؤهل ✓' : 'غير مؤهل حتى الآن';
                $eligClass = ($attOk && $scoreOk)
                    ? config('brand.classes.badge_secondary')
                    : config('brand.classes.badge_danger');
            } else {
                $eligDisplay = '—';
                $eligClass = null;
            }

            $timingClass = 'bg-slate-100 text-slate-600';
            if ($timingLabel) {
                if (str_starts_with($timingLabel, 'متبق')) {
                    $timingClass = 'bg-[#e9eff6] text-[#335483]';
                } elseif ($timingLabel === 'جار') {
                    $timingClass = 'bg-emerald-50 text-emerald-700';
                }
            }
        @endphp

        <article class="portal-program-card p-4 sm:p-5" aria-label="{{ $program?->title ?? 'برنامج' }}">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start gap-2">
                        @if ($programShowUrl)
                        <a href="{{ $programShowUrl }}" class="text-base font-bold text-slate-900 transition hover:text-[#335483] sm:text-lg">
                            {{ $program->title }}
                        </a>
                        @else
                        <h2 class="text-base font-bold text-slate-900 sm:text-lg">—</h2>
                        @endif
                    </div>

                    <div class="mt-2.5 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusLabels[$sv] ?? $sv }}
                        </span>

                        @if ($programShowUrl && $timingLabel)
                        <a href="{{ $programShowUrl }}" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold transition hover:opacity-90 {{ $timingClass }}">
                            {{ $timingLabel }}
                        </a>
                        @elseif ($timingLabel)
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $timingClass }}">{{ $timingLabel }}</span>
                        @else
                        <span class="text-xs text-slate-400">وقت البرنامج غير محدد</span>
                        @endif

                        @if ($programShowUrl)
                        <a href="{{ $programShowUrl }}" class="text-xs font-semibold text-[#335483]/90 transition hover:underline">
                            تفاصيل البرنامج
                        </a>
                        @endif
                    </div>
                </div>

                <div class="portal-program-actions shrink-0 lg:justify-end">
                    @if ($isAccepted && $checkInUrl)
                    <a
                        href="{{ $checkInUrl }}"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                        style="background:#335483"
                    >
                        @if ($isInPerson)
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                            QR الحضور
                        @else
                            التحضير
                        @endif
                    </a>
                    @elseif ($checkInUrl)
                    <span class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-400 ring-1 ring-slate-200/80">
                        التحضير: بعد القبول
                    </span>
                    @endif

                    @if ($whatsappUrl)
                    <a
                        href="{{ $whatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                        style="background:#25D366"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        انضم للواتساب
                    </a>
                    @elseif ($isAccepted)
                    <span class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-400 ring-1 ring-slate-200/80">
                        واتساب غير متاح
                    </span>
                    @else
                    <span class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-400 ring-1 ring-slate-200/80">
                        واتساب: بعد القبول
                    </span>
                    @endif

                    @if ($reg->certificate)
                        @if ($reg->certificate->downloadUrl())
                        <a href="{{ $reg->certificate->downloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-xl bg-brand px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95">
                            تحميل الشهادة
                        </a>
                        @else
                        <span class="inline-flex items-center rounded-xl bg-[#e9eff6] px-3.5 py-2 text-xs font-semibold text-[#335483] ring-1 ring-[#c5d4e4]/70">
                            شهادة صادرة
                        </span>
                        @endif
                    @else
                    <span class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-400 ring-1 ring-slate-200/80">
                        لا شهادة بعد
                    </span>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
                <div class="portal-program-metric">
                    <p class="portal-program-metric__label">وقت البرنامج</p>
                    <div class="portal-program-metric__value">
                        @if ($programShowUrl && $timingLabel)
                        <a href="{{ $programShowUrl }}" class="text-[#335483] transition hover:underline">{{ $timingLabel }}</a>
                        @elseif ($timingLabel)
                        {{ $timingLabel }}
                        @else
                        <span class="font-medium text-slate-400">—</span>
                        @endif
                    </div>
                </div>

                <div class="portal-program-metric">
                    <p class="portal-program-metric__label">نسبة الحضور</p>
                    <p class="portal-program-metric__value tabular-nums">{{ $attendanceDisplay }}</p>
                </div>

                <div class="portal-program-metric">
                    <p class="portal-program-metric__label">الدرجة</p>
                    <p class="portal-program-metric__value tabular-nums">{{ $scoreDisplay }}</p>
                </div>

                <div class="portal-program-metric">
                    <p class="portal-program-metric__label">أهلية الشهادة</p>
                    <div class="portal-program-metric__value">
                        @if ($eligClass)
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $eligClass }}">{{ $eligDisplay }}</span>
                        @else
                        <span class="font-medium text-slate-400">{{ $eligDisplay }}</span>
                        @endif
                    </div>
                </div>

                <div class="portal-program-metric col-span-2 sm:col-span-1">
                    <p class="portal-program-metric__label">التحضير</p>
                    <div class="portal-program-metric__value">
                        @if ($isAccepted && $checkInUrl)
                            @if ($isInPerson)
                            QR الحضور
                            @else
                            التحضير عن بُعد
                            @endif
                        @elseif ($checkInUrl)
                        <span class="font-medium text-slate-400">بعد القبول</span>
                        @else
                        <span class="font-medium text-slate-400">—</span>
                        @endif
                    </div>
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
</div>
@endsection
