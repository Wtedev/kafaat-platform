@php
use App\Enums\ProgramDeliveryMode;
use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Support\RegistrationEligibilitySupport;
use App\Support\TrainingProgramExtrasSupport;

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
            href="{{ route('home') }}#programs"
            class="inline-flex shrink-0 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
            style="background:#335483"
        >
            استكشف البرامج
        </a>
    </section>

@if (session('attendance_success'))
<div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
    {{ session('attendance_success') }}
</div>
@endif

@if (session('attendance_error'))
<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
    {{ session('attendance_error') }}
</div>
@endif

@if ($registrations->isEmpty())
    <x-portal.empty-state
        title="لا توجد برامج مسجّلة"
        description="لم تسجّل في أي برنامج تدريبي بعد. تصفّح البرامج المنشورة وسجّل عند توفر مقعد."
    >
        <a href="{{ route('home') }}#programs" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف البرامج</a>
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
            $programShowUrl = ($program && filled($program->slug))
                ? route('public.programs.show', $program)
                : null;
            $isInPersonToday = ($reg->today_prep_type ?? null) === \App\Enums\ProgramPrepDayType::InPerson;
            $isRemoteToday = ($reg->today_prep_type ?? null) === \App\Enums\ProgramPrepDayType::Remote;
            $attendancePass = $reg->attendance_pass ?? [];
            $liveSession = $reg->live_session ?? null;
            $canOpenAttendance = $isAccepted && $program;
            $attendanceModalId = null;
            if ($canOpenAttendance && $isInPersonToday) {
                $attendanceModalId = 'program-attendance-qr-'.$program->id;
            } elseif ($canOpenAttendance && $isRemoteToday) {
                $attendanceModalId = 'program-attendance-remote-'.$program->id;
            }
            $showQrModal = $canOpenAttendance && $isInPersonToday && ! empty($attendancePass['qr_data_uri']);
            $canRemoteCheckIn = $canOpenAttendance
                && $isRemoteToday
                && $liveSession !== null
                && $liveSession->isActive()
                && ! ($reg->today_present ?? false);
            $alreadyPresentToday = (bool) ($reg->today_present ?? false);
            $markedAtLabel = null;
            if ($alreadyPresentToday && $reg->today_marked_at) {
                $markedAt = $reg->today_marked_at->timezone(config('app.timezone'));
                $markedAtLabel = ar_date($markedAt, 'h:mm a');
            }
            $sessionEndedToday = (bool) ($reg->live_session_ended_today ?? false);

            $effectiveAttendance = $reg->effectiveAttendancePercentage();
            $scoreValue = $reg->score !== null ? (float) $reg->score : null;
            $attendanceOfHundred = $effectiveAttendance === null
                ? '—'
                : RegistrationEligibilitySupport::plainAmount($effectiveAttendance).' من 100';
            $programStartLabel = $program?->start_date ? ar_date($program->start_date) : '—';
            $competencyTrack = $program?->competency_track;
            $competencyLabel = $competencyTrack instanceof \App\Enums\CompetencyTrack
                ? $competencyTrack->label()
                : '—';
            $certificateCopy = RegistrationEligibilitySupport::beneficiaryCertificateCopy(
                $effectiveAttendance,
                $scoreValue,
                $isAccepted,
                $reg->certificate !== null,
            );
            $surveysByType = ($program?->surveys ?? collect())->keyBy(
                fn ($survey) => $survey->type instanceof SurveyType ? $survey->type->value : (string) $survey->type,
            );
            $surveySlots = [
                ['label' => 'الاستبيان القبلي', 'type' => SurveyType::Pre],
                ['label' => 'استبيان الرضا', 'type' => SurveyType::Satisfaction],
                ['label' => 'الاستبيان البعدي', 'type' => SurveyType::Post],
            ];

            $checkInLabel = $isInPersonToday ? 'QR الحضور' : ($isRemoteToday ? 'تسجيل حضوري' : 'التحضير');
        @endphp

        <li>
            <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-[#c5d4e4]" aria-label="{{ $program?->title ?? 'برنامج' }}">
                <x-portal.card-header variant="bar" />
                <div class="flex items-start gap-3 px-4 py-4 sm:gap-4 sm:px-5 sm:py-5">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e9eff6] text-[#335483]" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                    </span>

                    <div class="min-w-0 flex-1 text-right">
                        <p class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] leading-5 text-gray-500">
                            <span>تاريخ البرنامج <span class="font-semibold text-gray-800">{{ $programStartLabel }}</span></span>
                            <span aria-hidden="true">·</span>
                            <span>حالة القبول <span class="font-semibold text-gray-800">{{ $statusLabels[$sv] ?? $sv }}</span></span>
                            <span aria-hidden="true">·</span>
                            <span>مسار الكفاءة <span class="font-semibold text-gray-800">{{ $competencyLabel }}</span></span>
                        </p>

                        @if ($programShowUrl)
                        <a href="{{ $programShowUrl }}" class="text-sm font-bold leading-snug text-[#335483] transition hover:opacity-80 sm:text-[0.95rem]">
                            {{ $program->title }}
                        </a>
                        @else
                        <h2 class="text-sm font-bold leading-snug text-[#335483] sm:text-[0.95rem]">—</h2>
                        @endif

                        @if ($programShowUrl)
                        <div class="mt-1">
                            <a href="{{ $programShowUrl }}" class="inline-flex items-center gap-1 text-[11px] font-semibold transition hover:opacity-80" style="color:#335483">
                                تفاصيل البرنامج
                                <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                        @endif

                        <div class="mt-3.5 flex flex-wrap items-center gap-2">
                            @if ($whatsappUrl)
                            <a
                                href="{{ $whatsappUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                                style="background:#25D366"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                انضم لمجموعة البرنامج
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

                            <button
                                type="button"
                                class="portal-card-toggle inline-flex h-9 w-9 items-center justify-center rounded-xl text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-[#e9eff6]"
                                aria-expanded="false"
                                aria-controls="portal-card-extra-{{ $reg->id }}"
                                aria-label="تفاصيل التسجيل"
                            >
                                <svg data-chevron class="h-4 w-4 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                            </button>

                            @if ($canOpenAttendance && $showQrModal)
                            <button
                                type="button"
                                class="portal-attendance-open inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                                style="background:#335483"
                                data-attendance-modal="{{ $attendanceModalId }}"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                {{ $checkInLabel }}
                            </button>
                            @elseif ($canOpenAttendance && $isRemoteToday && $alreadyPresentToday)
                            <span class="inline-flex items-center rounded-xl bg-[#e6f5f6] px-3 py-2 text-xs font-semibold text-[#1a9399] ring-1 ring-[#b8e0e2]">
                                تم تسجيل حضورك{{ $markedAtLabel ? ' الساعة '.$markedAtLabel : '' }}
                            </span>
                            @elseif ($canOpenAttendance && $isRemoteToday && $canRemoteCheckIn)
                            <button
                                type="button"
                                class="portal-attendance-open inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                                style="background:#335483"
                                data-attendance-modal="{{ $attendanceModalId }}"
                            >
                                تسجيل حضوري
                            </button>
                            @elseif ($canOpenAttendance && $isRemoteToday && $sessionEndedToday)
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
                                انتهت جلسة التحضير
                            </span>
                            @elseif ($canOpenAttendance && $isRemoteToday)
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
                                بانتظار فتح جلسة التحضير
                            </span>
                            @elseif ($isAccepted)
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
                                لا يوجد تحضير اليوم
                            </span>
                            @elseif ($program)
                            <span class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-medium text-gray-400 ring-1 ring-gray-200">
                                التحضير: بعد القبول
                            </span>
                            @endif
                        </div>

                        <div id="portal-card-extra-{{ $reg->id }}" class="mt-3 border-t border-gray-100 pt-3" hidden>
                            <ul class="space-y-2.5 text-xs" role="list">
                                <li class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-gray-500">حضور المستفيد</span>
                                    <span class="font-bold tabular-nums text-gray-900">{{ $attendanceOfHundred }}</span>
                                </li>
                                @foreach ($surveySlots as $slot)
                                    @php
                                        $survey = $surveysByType->get($slot['type']->value);
                                        $surveyAnswered = $survey && ($answeredSurveyKeys ?? collect())->has($reg->id.':'.$survey->id);
                                    @endphp
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="font-medium text-gray-500">{{ $slot['label'] }}</span>
                                        @if (! $survey)
                                        <span class="font-semibold text-gray-400">غير متاح</span>
                                        @elseif ($surveyAnswered)
                                        <span class="font-semibold text-[#1a9399]">مكتمل</span>
                                        @elseif ($survey->isOpen())
                                        <a href="{{ $survey->publicUrl() }}" class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-semibold text-white transition hover:opacity-90" style="background:#335483">تعبئة</a>
                                        @else
                                        <span class="font-semibold text-gray-400">مغلق</span>
                                        @endif
                                    </li>
                                @endforeach
                                <li class="flex items-start justify-between gap-3">
                                    <span class="font-medium text-gray-500">الشهادة</span>
                                    <span class="text-left">
                                        <span class="block font-bold text-gray-900">{{ $certificateCopy['status'] }}</span>
                                        <span class="mt-0.5 block text-[11px] leading-5 text-gray-500">{{ $certificateCopy['reason'] }}</span>
                                        @if ($reg->certificate?->downloadUrl())
                                        <a href="{{ $reg->certificate->downloadUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-semibold text-white transition hover:opacity-90" style="background:#335483">تحميل الشهادة</a>
                                        @endif
                                    </span>
                                </li>
                            </ul>
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

@if ($registrations->isNotEmpty())
@push('styles')
<style>
    .portal-attendance-modal {
        position: fixed;
        inset: 0;
        z-index: 100;
        margin: auto;
        width: min(calc(100% - 2rem), 28rem);
        max-height: calc(100vh - 2rem);
        overflow: auto;
        border: 1px solid #c5d4e4;
        padding: 0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .portal-attendance-modal::backdrop {
        background: rgba(15, 23, 42, 0.45);
    }
</style>
@endpush

@push('modals')
    @foreach ($registrations as $reg)
        @php
            $program = $reg->trainingProgram;
            if (! $program) {
                continue;
            }
            $sv = $reg->status->value;
            $isAccepted = in_array($sv, [
                \App\Enums\RegistrationStatus::Approved->value,
                \App\Enums\RegistrationStatus::Completed->value,
            ], true);
            if (! $isAccepted) {
                continue;
            }
            $isInPersonToday = ($reg->today_prep_type ?? null) === \App\Enums\ProgramPrepDayType::InPerson;
            $isRemoteToday = ($reg->today_prep_type ?? null) === \App\Enums\ProgramPrepDayType::Remote;
            $attendancePass = $reg->attendance_pass ?? [];
            $liveSession = $reg->live_session ?? null;
            $canRemoteCheckIn = $isRemoteToday
                && $liveSession !== null
                && $liveSession->isActive();
        @endphp
        @if ($isInPersonToday && ! empty($attendancePass['qr_data_uri']))
            <x-portal.program-attendance-qr-modal
                :program-id="$program->id"
                :qr-data-uri="$attendancePass['qr_data_uri']"
                :pass-code="$attendancePass['pass_code'] ?? null"
                :venue-label="$attendancePass['venue_label'] ?? null"
            />
        @elseif ($isRemoteToday)
            <x-portal.program-attendance-remote-modal
                :program-id="$program->id"
                :program-title="$program->title"
                :status-url="route('portal.programs.attendance.session', $program)"
                :check-in-url="route('portal.programs.attendance.check-in', $program)"
                :initial-active="$canRemoteCheckIn"
                :initial-expires-at-ms="$canRemoteCheckIn ? $liveSession->expires_at->getTimestamp() * 1000 : null"
            />
        @endif
    @endforeach
@endpush

@push('scripts')
<script>
(function () {
    function openPortalAttendanceModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal || typeof modal.showModal !== 'function') {
            return;
        }
        modal.showModal();
    }

    document.querySelectorAll('.portal-attendance-open').forEach(function (button) {
        button.addEventListener('click', function () {
            openPortalAttendanceModal(button.dataset.attendanceModal);
        });
    });

    document.querySelectorAll('.portal-card-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById(button.getAttribute('aria-controls'));
            if (! panel) {
                return;
            }
            var open = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = ! open;
            var chevron = button.querySelector('[data-chevron]');
            if (chevron) {
                chevron.classList.toggle('rotate-180', open);
            }
        });
    });

    document.querySelectorAll('.portal-attendance-modal').forEach(function (modal) {
        modal.querySelectorAll('.portal-attendance-modal-close').forEach(function (closeBtn) {
            closeBtn.addEventListener('click', function () {
                modal.close();
            });
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.close();
            }
        });

        var waiting = modal.querySelector('[data-portal-attendance-waiting]');
        var sessionBox = modal.querySelector('[data-portal-attendance-session]');
        if (waiting && sessionBox) {
            var observer = new MutationObserver(function () {
                waiting.hidden = !sessionBox.hidden;
            });
            observer.observe(sessionBox, { attributes: true, attributeFilter: ['hidden'] });
            waiting.hidden = !sessionBox.hidden;
        }
    });

    @if (! empty($openAttendanceProgramId))
    var qrModal = document.getElementById('program-attendance-qr-{{ $openAttendanceProgramId }}');
    var remoteModal = document.getElementById('program-attendance-remote-{{ $openAttendanceProgramId }}');
    if (qrModal && typeof qrModal.showModal === 'function') {
        qrModal.showModal();
    } else if (remoteModal && typeof remoteModal.showModal === 'function') {
        remoteModal.showModal();
    }
    @endif
})();
</script>
@endpush
@endif
@endsection
