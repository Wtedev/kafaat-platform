@php
    /** @var array<string, mixed> $header */
    /** @var array<string, mixed>|null $primary_action */
    /** @var array<string, mixed> $summary */
    /** @var list<array<string, mixed>> $attendance_log */
    /** @var list<array<string, mixed>> $timeline */
    /** @var array<string, mixed> $certificate */
    /** @var array<string, mixed> $enrollment */
    $statusTone = [
        'present' => ['icon' => '✓', 'class' => 'bg-[#e6f5f6] text-[#1a9399] ring-[#b8e0e2]'],
        'absent' => ['icon' => '✕', 'class' => 'bg-[#fdeeed] text-[#ec6056] ring-[#f5c4c0]'],
        'upcoming' => ['icon' => '○', 'class' => 'bg-[#fef6e6] text-[#335483] ring-[#f5dfa8]'],
        'not_required' => ['icon' => '–', 'class' => 'bg-gray-100 text-gray-600 ring-gray-200'],
    ];
@endphp

@extends('layouts.portal')
@section('title', $header['title'])

@section('content')
<div class="mx-auto w-full max-w-6xl space-y-5">
    <nav class="text-sm" aria-label="التنقل">
        <a href="{{ route('portal.programs') }}" class="inline-flex items-center gap-1.5 font-medium text-[#335483] transition hover:opacity-80">
            <span aria-hidden="true">→</span>
            برامجي
        </a>
    </nav>

    @if (session('attendance_success'))
        <div class="{{ config('brand.classes.alert_success') }}" role="status">{{ session('attendance_success') }}</div>
    @endif
    @if (session('attendance_error'))
        <div class="{{ config('brand.classes.alert_danger') }}" role="alert">{{ session('attendance_error') }}</div>
    @endif

    <section class="overflow-hidden rounded-3xl border border-[#c5d4e4]/70 bg-white shadow-sm" aria-labelledby="program-detail-title">
        <x-portal.card-header variant="bar" />
        <div class="px-4 py-5 sm:px-6 sm:py-6">
            <div class="flex flex-col gap-4">
                <div class="min-w-0">
                    <h1 id="program-detail-title" class="text-xl font-bold leading-snug text-[#335483] sm:text-2xl">{{ $header['title'] }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $header['registration_status']['class'] }}">
                            {{ $header['registration_status']['label'] }}
                        </span>
                        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $header['program_status']['class'] }}">
                            البرنامج: {{ $header['program_status']['label'] }}
                        </span>
                        <span class="inline-flex items-center rounded-lg bg-[#e9eff6] px-2.5 py-1 text-xs font-semibold text-[#335483] ring-1 ring-[#c5d4e4]">
                            {{ $header['delivery']['label'] }}
                        </span>
                    </div>
                </div>

                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                        <dt class="text-[11px] font-medium text-gray-500">بداية البرنامج</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $header['start_label'] }}</dd>
                    </div>
                    <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                        <dt class="text-[11px] font-medium text-gray-500">نهاية البرنامج</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $header['end_label'] }}</dd>
                    </div>
                    <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                        <dt class="text-[11px] font-medium text-gray-500">الموقع / الانضمام</dt>
                        <dd class="mt-1 text-sm font-semibold leading-relaxed text-gray-900">{{ $header['location']['label'] }}</dd>
                    </div>
                </dl>

                @if ($header['timing_label'])
                    <p class="text-xs text-gray-500">{{ $header['timing_label'] }} — الأوقات بتوقيت الرياض.</p>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    @if ($primary_action)
                        @if ($primary_action['href'])
                            <a
                                href="{{ $primary_action['href'] }}"
                                class="inline-flex items-center justify-center rounded-xl bg-[#335483] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
                            >
                                {{ $primary_action['label'] }}
                            </a>
                        @elseif ($primary_action['modal_id'])
                            <button
                                type="button"
                                class="portal-attendance-open inline-flex items-center justify-center rounded-xl bg-[#335483] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
                                data-attendance-modal="{{ $primary_action['modal_id'] }}"
                            >
                                {{ $primary_action['label'] }}
                            </button>
                        @endif
                    @endif
                    @if ($header['catalog_url'])
                        <a href="{{ $header['catalog_url'] }}" class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-semibold text-[#335483] ring-1 ring-[#c5d4e4] transition hover:bg-[#e9eff6]">
                            الوصف العام
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="participation-summary-title">
        <h2 id="participation-summary-title" class="mb-3 text-base font-bold text-gray-900">ملخص المشاركة</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-2xl border border-gray-100 bg-white px-3.5 py-3.5 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500">أيام الحضور</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-[#335483]">{{ en_num($summary['present']) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white px-3.5 py-3.5 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500">أيام الغياب</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ en_num($summary['absent']) }}</p>
                <p class="mt-1 text-[11px] text-gray-400">بعد انتهاء فرصة التحضير فقط</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white px-3.5 py-3.5 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500">إجمالي الأيام</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ en_num($summary['total']) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white px-3.5 py-3.5 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500">نسبة الحضور</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ $summary['percentage_label'] }}</p>
                <p class="mt-1 text-[11px] leading-relaxed text-gray-400">{{ $summary['percentage_note'] }}</p>
            </div>
            <div class="col-span-2 rounded-2xl border border-gray-100 bg-white px-3.5 py-3.5 shadow-sm lg:col-span-1">
                <p class="text-[11px] font-medium text-gray-500">أهلية الشهادة</p>
                <p class="mt-2">
                    <span class="inline-flex rounded-lg px-2 py-0.5 text-xs font-semibold {{ $summary['eligibility']['class'] }}">
                        {{ $summary['eligibility']['label'] }}
                    </span>
                </p>
                <p class="mt-2 text-[11px] leading-relaxed text-gray-500">{{ $summary['eligibility']['reason'] }}</p>
            </div>
        </div>
        @if ($summary['score'] !== null)
            <p class="mt-3 text-sm text-gray-600">الدرجة الحالية: <span class="font-semibold text-gray-900">{{ $summary['score_label'] }}</span></p>
        @endif
    </section>

    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm" aria-labelledby="attendance-log-title">
        <x-portal.card-header variant="soft" title="سجل الحضور" heading="h2" />
        <div class="px-4 py-4 sm:px-5">
            @if ($attendance_log === [])
                <x-portal.empty-state
                    title="لا يوجد سجل حضور"
                    description="لم تُسجَّل أيام تحضير لهذا البرنامج بعد."
                />
            @else
                <ul class="space-y-3 sm:hidden" role="list">
                    @foreach ($attendance_log as $row)
                        @php $tone = $statusTone[$row['status_key']] ?? $statusTone['upcoming']; @endphp
                        <li class="rounded-2xl border border-gray-100 bg-[#F7FAFC] p-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $row['date_label'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $row['type_label'] }}</p>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $tone['class'] }}">
                                    <span aria-hidden="true">{{ $tone['icon'] }}</span>
                                    {{ $row['status_label'] }}
                                </span>
                            </div>
                            <dl class="mt-3 grid grid-cols-1 gap-1.5 text-xs text-gray-600">
                                <div>
                                    <dt class="text-gray-400">وقت التسجيل</dt>
                                    <dd class="font-medium text-gray-800">{{ $row['marked_at'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-400">طريقة التحضير</dt>
                                    <dd class="font-medium text-gray-800">{{ $row['method_label'] }}</dd>
                                </div>
                                <div>
                                    <dt class="sr-only">ملاحظة الجلسة</dt>
                                    <dd>{{ $row['session_message'] }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                <div class="hidden overflow-x-visible sm:block">
                    <table class="w-full text-right text-sm">
                        <caption class="sr-only">سجل حضور المستفيد حسب أيام البرنامج</caption>
                        <thead>
                            <tr class="border-b border-gray-100 text-xs text-gray-500">
                                <th scope="col" class="py-2 pe-3 font-medium">التاريخ</th>
                                <th scope="col" class="py-2 pe-3 font-medium">اليوم</th>
                                <th scope="col" class="py-2 pe-3 font-medium">النوع</th>
                                <th scope="col" class="py-2 pe-3 font-medium">الحالة</th>
                                <th scope="col" class="py-2 pe-3 font-medium">وقت التسجيل</th>
                                <th scope="col" class="py-2 font-medium">الطريقة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendance_log as $row)
                                @php $tone = $statusTone[$row['status_key']] ?? $statusTone['upcoming']; @endphp
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="py-3 pe-3 font-medium text-gray-900">{{ $row['date_label'] }}</td>
                                    <td class="py-3 pe-3 text-gray-600">{{ $row['day_name'] }}</td>
                                    <td class="py-3 pe-3 text-gray-600">{{ $row['type_label'] }}</td>
                                    <td class="py-3 pe-3">
                                        <span class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $tone['class'] }}">
                                            <span aria-hidden="true">{{ $tone['icon'] }}</span>
                                            {{ $row['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 pe-3 tabular-nums text-gray-600">{{ $row['marked_at'] ?? '—' }}</td>
                                    <td class="py-3 text-gray-600">{{ $row['method_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm" aria-labelledby="timeline-title">
        <x-portal.card-header variant="soft" title="الجدول الزمني" heading="h2" />
        <div class="px-4 py-4 sm:px-5">
            @if ($timeline === [])
                <x-portal.empty-state
                    title="لا توجد جلسات قادمة"
                    description="انتهت أيام التحضير المسجّلة، أو لم يُحدد جدول بعد."
                />
            @else
                <ol class="space-y-3">
                    @foreach ($timeline as $row)
                        <li class="rounded-2xl border border-gray-100 bg-[#F7FAFC] px-3.5 py-3.5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $row['date_label'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $row['type_label'] }} · {{ $row['status_label'] }}</p>
                                    <p class="mt-1 text-xs text-gray-600">{{ $row['session_message'] }}</p>
                                </div>
                                @if ($row['join_available'] && $primary_action && $primary_action['modal_id'])
                                    <button
                                        type="button"
                                        class="portal-attendance-open inline-flex shrink-0 items-center justify-center rounded-xl bg-[#335483] px-3.5 py-2 text-xs font-semibold text-white"
                                        data-attendance-modal="{{ $primary_action['modal_id'] }}"
                                    >
                                        الانضمام للجلسة
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>

    @if ($show_grades && $grades)
        <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm" aria-labelledby="grades-title">
            <x-portal.card-header variant="soft" title="التقييم والدرجة" heading="h2" />
            <div class="grid gap-3 px-4 py-4 sm:grid-cols-3 sm:px-5">
                <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                    <p class="text-[11px] text-gray-500">الدرجة الحالية</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ $grades['score_label'] }}</p>
                </div>
                <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                    <p class="text-[11px] text-gray-500">الدرجة الكاملة</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">100</p>
                </div>
                <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                    <p class="text-[11px] text-gray-500">حالة الاجتياز</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $grades['pass_label'] }}</p>
                    @if ($grades['average_label'])
                        <p class="mt-1 text-xs text-gray-500">المتوسط: {{ $grades['average_label'] }}</p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm" aria-labelledby="certificate-title">
        <x-portal.card-header variant="soft" title="الشهادة" heading="h2" />
        <div class="space-y-3 px-4 py-4 sm:px-5">
            <p>
                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $certificate['class'] ?? $summary['eligibility']['class'] }}">
                    {{ $certificate['label'] }}
                </span>
            </p>
            <p class="text-sm leading-relaxed text-gray-600">{{ $certificate['reason'] }}</p>
            <div>
                <h3 class="text-xs font-semibold text-gray-500">شروط الشهادة</h3>
                <ul class="mt-1.5 list-disc space-y-1 pe-5 text-sm text-gray-700">
                    @foreach ($certificate['conditions'] as $condition)
                        <li>{{ $condition }}</li>
                    @endforeach
                </ul>
            </div>
            @if ($certificate['download_url'])
                <a href="{{ $certificate['download_url'] }}" class="inline-flex items-center justify-center rounded-xl bg-[#335483] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                    تحميل الشهادة
                </a>
            @elseif ($certificate['issued'])
                <p class="text-sm text-gray-500">شهادة صادرة، وملف التحميل غير متاح حالياً.</p>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm" aria-labelledby="enrollment-title">
        <x-portal.card-header variant="soft" title="معلومات التسجيل" heading="h2" />
        <dl class="grid grid-cols-1 gap-3 px-4 py-4 sm:grid-cols-2 sm:px-5">
            <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                <dt class="text-[11px] text-gray-500">رقم التسجيل</dt>
                <dd class="mt-1 text-sm font-semibold tabular-nums text-gray-900">{{ en_num($enrollment['id']) }}</dd>
            </div>
            <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                <dt class="text-[11px] text-gray-500">تاريخ التسجيل</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $enrollment['registered_at'] }}</dd>
            </div>
            <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                <dt class="text-[11px] text-gray-500">تاريخ القبول</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $enrollment['approved_at'] ?? '—' }}</dd>
            </div>
            <div class="rounded-2xl bg-[#F7FAFC] px-3.5 py-3">
                <dt class="text-[11px] text-gray-500">آخر تحديث للحالة</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $enrollment['updated_at'] }}</dd>
            </div>
            @if ($enrollment['rejected_reason'])
                <div class="sm:col-span-2 rounded-2xl bg-[#fdeeed] px-3.5 py-3">
                    <dt class="text-[11px] text-[#ec6056]">سبب الرفض</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $enrollment['rejected_reason'] }}</dd>
                </div>
            @endif
            @if ($enrollment['whatsapp_url'])
                <div class="sm:col-span-2">
                    <a href="{{ $enrollment['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-[#335483] underline-offset-2 hover:underline">
                        رابط مجموعة الواتساب
                    </a>
                </div>
            @endif
        </dl>
    </section>
</div>

@if ($accepted && $program)
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
        @if ($today_prep_type === \App\Enums\ProgramPrepDayType::InPerson && ! empty($attendance_pass['qr_data_uri']))
            <x-portal.program-attendance-qr-modal
                :program-id="$program->id"
                :qr-data-uri="$attendance_pass['qr_data_uri']"
                :pass-code="$attendance_pass['pass_code'] ?? null"
                :venue-label="$attendance_pass['venue_label'] ?? null"
            />
        @elseif ($today_prep_type === \App\Enums\ProgramPrepDayType::Remote)
            <x-portal.program-attendance-remote-modal
                :program-id="$program->id"
                :program-title="$program->title"
                :status-url="route('portal.programs.attendance.session', $program)"
                :check-in-url="route('portal.programs.attendance.check-in', $program)"
                :initial-active="$live_session_active"
                :initial-expires-at-ms="$live_session_active ? $live_session->expires_at->getTimestamp() * 1000 : null"
            />
        @endif
    @endpush

    @push('scripts')
    <script>
    (function () {
        function openPortalAttendanceModal(modalId) {
            var modal = document.getElementById(modalId);
            if (!modal || typeof modal.showModal !== 'function') return;
            modal.showModal();
        }
        document.querySelectorAll('.portal-attendance-open').forEach(function (button) {
            button.addEventListener('click', function () {
                openPortalAttendanceModal(button.dataset.attendanceModal);
            });
        });
        document.querySelectorAll('.portal-attendance-modal').forEach(function (modal) {
            modal.querySelectorAll('.portal-attendance-modal-close').forEach(function (closeBtn) {
                closeBtn.addEventListener('click', function () { modal.close(); });
            });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) modal.close();
            });
        });
    })();
    </script>
    @endpush
@endif
@endsection
