@php
use App\Enums\ProgramDeliveryMode;
use App\Enums\RegistrationStatus;

$statusLabels = [
    RegistrationStatus::Pending->value => 'قيد المراجعة',
    RegistrationStatus::Approved->value => 'مقبول',
    RegistrationStatus::Rejected->value => 'مرفوض',
    RegistrationStatus::Cancelled->value => 'ملغي',
    RegistrationStatus::Completed->value => 'مكتمل',
];
$statusColors = RegistrationStatus::badgeClasses();
$sv = $registration->status->value;
$isAccepted = in_array($sv, [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value], true);
$isInPerson = $trainingProgram->delivery_mode === ProgramDeliveryMode::InPerson;
$isRemote = $trainingProgram->delivery_mode === ProgramDeliveryMode::Remote;
$canCheckIn = $isAccepted
    && $isRemote
    && $liveSession !== null
    && $liveSession->isActive();
$attendance = $attendancePass ?? [];
$showQr = $isAccepted && $isInPerson && ! empty($attendance['qr_data_uri']);
@endphp

@extends('layouts.portal')
@section('title', $trainingProgram->title)
@section('content')

<div class="mb-6">
    <a href="{{ route('portal.programs') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-80" style="color:#335483">
        ← البرامج واللقاءات
    </a>
</div>

<h1 class="mb-2 text-2xl font-bold text-gray-900">{{ $trainingProgram->title }}</h1>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
        {{ $statusLabels[$sv] ?? $sv }}
    </span>
    @if ($trainingProgram->delivery_mode)
    <span class="rounded-full bg-[#e9eff6] px-2.5 py-0.5 text-xs font-medium text-[#335483]">
        {{ $trainingProgram->delivery_mode->label() }}
    </span>
    @endif
    @if ($trainingProgram->start_date)
    <span class="text-sm text-gray-500">البداية: {{ $trainingProgram->start_date->format('Y/m/d') }}</span>
    @endif
    @if ($trainingProgram->end_date)
    <span class="text-sm text-gray-500">النهاية: {{ $trainingProgram->end_date->format('Y/m/d') }}</span>
    @endif
</div>

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

@if ($showQr)
<div class="mb-6 overflow-hidden rounded-2xl border border-[#c5d4e4]/70 bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900">بطاقة الحضور (QR)</h2>
    <p class="mt-1 text-sm text-gray-500">
        أظهر رمز QR عند الوصول لتسجيل تحضيرك.
        @if (! empty($attendance['venue_label']))
            المكان: {{ $attendance['venue_label'] }}
        @endif
    </p>
    <div class="mt-5 flex flex-col items-center">
        <div class="inline-block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-[#c5d4e4]/50">
            <img
                src="{{ $attendance['qr_data_uri'] }}"
                alt="رمز QR للحضور"
                class="mx-auto h-[180px] w-[180px] sm:h-[220px] sm:w-[220px]"
                width="220"
                height="220"
            >
        </div>
        @if (! empty($attendance['pass_code']))
        <p class="mt-3 font-mono text-xs tracking-wide text-gray-400" dir="ltr">{{ $attendance['pass_code'] }}</p>
        @endif
    </div>
</div>
@elseif ($isInPerson && ! $isAccepted)
<div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
    بطاقة الحضور (QR) متاحة بعد قبول تسجيلك في البرنامج.
</div>
@endif

@if ($isRemote)
    @if ($isAccepted)
    <x-portal-attendance-session
        :status-url="route('portal.programs.attendance.session', $trainingProgram)"
        :check-in-url="route('portal.programs.attendance.check-in', $trainingProgram)"
        :initial-active="$canCheckIn"
        :initial-expires-at-ms="$canCheckIn ? $liveSession->expires_at->getTimestamp() * 1000 : null"
    />
    <div class="mb-6 rounded-2xl border border-dashed border-[#c5d4e4] bg-[#F7FAFC] px-5 py-4 text-sm text-gray-600" data-portal-attendance-waiting @if ($canCheckIn) hidden @endif>
        <p class="font-semibold text-[#335483]">التحضير عن بُعد</p>
        <p class="mt-1">ستظهر خانة تسجيل التحضير هنا تلقائياً عند فتح جلسة الحضور من الإدارة.</p>
    </div>
    @else
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
        التحضير عن بُعد متاح بعد قبول تسجيلك في البرنامج.
    </div>
    @endif
@endif

<div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-700 mb-2">نبذة عن البرنامج</h2>
    <div class="text-gray-600 leading-relaxed text-right font-sans">
        @php
            $programBody = trim((string) ($trainingProgram->description ?: ''));
            $programIsRichHtml = $programBody !== '' && preg_match('/<[a-z][\s\S]*>/i', $programBody);
            $hasSessionTopics = (bool) $trainingProgram->session_topics_enabled
                && filled(\App\Support\TrainingProgramExtrasSupport::publicSessionTopics($trainingProgram));
            $hasPresenters = filled(\App\Support\TrainingProgramExtrasSupport::publicProgramPresenters($trainingProgram));
        @endphp
        @if ($programBody === '' && ! $hasSessionTopics && ! $hasPresenters)
            —
        @elseif ($programBody !== '')
            @if ($programIsRichHtml)
                <div class="prose prose-lg max-w-none prose-headings:text-[#111827] prose-a:text-[#335483] prose-strong:text-[#111827]">{!! clean($programBody) !!}</div>
            @else
                <div class="whitespace-pre-line">{!! nl2br(e($programBody)) !!}</div>
            @endif
        @endif

        <x-public.program-session-topics
            :enabled="(bool) $trainingProgram->session_topics_enabled"
            :topics="$trainingProgram->session_topics"
            @class([
                'mt-6 border-t border-[#c5d4e4]/70 pt-6' => $programBody !== '',
                'mt-0 border-0 pt-0' => $programBody === '',
            ])
        />

        <x-public.program-presenters
            :presenters="$trainingProgram->program_presenters"
            @class([
                'mt-6 border-t border-[#c5d4e4]/70 pt-6' => $programBody !== '' || $hasSessionTopics,
                'mt-0 border-0 pt-0' => $programBody === '' && ! $hasSessionTopics,
            ])
        />
    </div>
</div>

@endsection

@if ($isRemote && $isAccepted)
@push('scripts')
<script>
(function () {
    var waiting = document.querySelector('[data-portal-attendance-waiting]');
    var sessionBox = document.querySelector('[data-portal-attendance-session]');
    if (!waiting || !sessionBox) return;

    var observer = new MutationObserver(function () {
        waiting.hidden = !sessionBox.hidden;
    });
    observer.observe(sessionBox, { attributes: true, attributeFilter: ['hidden'] });
    waiting.hidden = !sessionBox.hidden;
})();
</script>
@endpush
@endif
