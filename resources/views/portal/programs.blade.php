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

    <ul class="space-y-3" role="list">
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

            $checkInLabel = $isInPerson ? 'QR الحضور' : 'التحضير';
            $checkInMeta = $isAccepted
                ? ($isInPerson ? 'QR الحضور' : 'التحضير عن بُعد')
                : 'بعد القبول';
        @endphp

        <li>
            <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-[#c5d4e4]" aria-label="{{ $program?->title ?? 'برنامج' }}">
                <x-portal.card-header variant="bar" />
                <div class="flex items-start gap-3 px-4 py-4 sm:gap-4 sm:px-5 sm:py-5">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                    </span>

                    <div class="min-w-0 flex-1 text-right">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                @if ($programShowUrl)
                                <a href="{{ $programShowUrl }}" class="text-sm font-bold leading-snug text-[#335483] transition hover:opacity-80 sm:text-[0.95rem]">
                                    {{ $program->title }}
                                </a>
                                @else
                                <h2 class="text-sm font-bold leading-snug text-[#335483] sm:text-[0.95rem]">—</h2>
                                @endif

                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $statusLabels[$sv] ?? $sv }}
                                    </span>

                                    @if ($timingLabel)
                                        @if ($programShowUrl)
                                        <a href="{{ $programShowUrl }}" class="text-xs font-medium text-gray-500 transition hover:text-[#335483]">{{ $timingLabel }}</a>
                                        @else
                                        <span class="text-xs font-medium text-gray-500">{{ $timingLabel }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">وقت البرنامج غير محدد</span>
                                    @endif

                                    @if ($programShowUrl)
                                    <a href="{{ $programShowUrl }}" class="inline-flex items-center gap-1 text-xs font-semibold transition hover:opacity-80" style="color:#335483">
                                        تفاصيل البرنامج
                                        <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <ul class="mt-3.5 space-y-2 border-t border-gray-100 pt-3.5" role="list">
                            <li class="flex items-center gap-2.5 text-right">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </span>
                                <span class="min-w-0 flex-1 text-xs font-medium text-gray-500">وقت البرنامج</span>
                                <span class="shrink-0 text-xs font-bold tabular-nums text-gray-900">
                                    @if ($programShowUrl && $timingLabel)
                                    <a href="{{ $programShowUrl }}" class="text-[#335483] transition hover:underline">{{ $timingLabel }}</a>
                                    @elseif ($timingLabel)
                                    {{ $timingLabel }}
                                    @else
                                    <span class="font-medium text-gray-400">—</span>
                                    @endif
                                </span>
                            </li>
                            <li class="flex items-center gap-2.5 text-right">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </span>
                                <span class="min-w-0 flex-1 text-xs font-medium text-gray-500">نسبة الحضور</span>
                                <span class="shrink-0 text-xs font-bold tabular-nums text-gray-900">{{ $attendanceDisplay }}</span>
                            </li>
                            <li class="flex items-center gap-2.5 text-right">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                                </span>
                                <span class="min-w-0 flex-1 text-xs font-medium text-gray-500">الدرجة</span>
                                <span class="shrink-0 text-xs font-bold tabular-nums text-gray-900">{{ $scoreDisplay }}</span>
                            </li>
                            <li class="flex items-center gap-2.5 text-right">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                </span>
                                <span class="min-w-0 flex-1 text-xs font-medium text-gray-500">أهلية الشهادة</span>
                                <span class="shrink-0 text-xs font-bold text-gray-900">
                                    @if ($eligClass)
                                    <span class="inline-flex rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ $eligClass }}">{{ $eligDisplay }}</span>
                                    @else
                                    <span class="font-medium text-gray-400">{{ $eligDisplay }}</span>
                                    @endif
                                </span>
                            </li>
                            <li class="flex items-center gap-2.5 text-right">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25V9m-3 0h12m-12 0l.53 9.01a2.25 2.25 0 002.246 2.115h6.448a2.25 2.25 0 002.246-2.115L18.75 9"/></svg>
                                </span>
                                <span class="min-w-0 flex-1 text-xs font-medium text-gray-500">التحضير</span>
                                <span class="shrink-0 text-xs font-bold text-gray-900">
                                    @if ($checkInUrl && $isAccepted)
                                    {{ $checkInMeta }}
                                    @elseif ($checkInUrl)
                                    <span class="font-medium text-gray-400">{{ $checkInMeta }}</span>
                                    @else
                                    <span class="font-medium text-gray-400">—</span>
                                    @endif
                                </span>
                            </li>
                        </ul>

                        <div class="mt-3.5 flex flex-wrap items-center gap-2">
                            @if ($isAccepted && $checkInUrl)
                            <a
                                href="{{ $checkInUrl }}"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                                style="background:#335483"
                            >
                                @if ($isInPerson)
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                @endif
                                {{ $checkInLabel }}
                            </a>
                            @elseif ($checkInUrl)
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
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
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
                                واتساب غير متاح
                            </span>
                            @else
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
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
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
                                لا شهادة بعد
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        </li>
        @endforeach
    </ul>

    @if ($registrations->hasPages())
    <div class="mt-6 rounded-2xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
        {{ $registrations->links() }}
    </div>
    @endif
@endif
</div>
@endsection
