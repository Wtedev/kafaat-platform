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
<h1 class="mb-6 text-2xl font-bold text-gray-900">البرامج واللقاءات</h1>

@if ($registrations->isEmpty())
<x-portal.empty-state
    title="لا توجد برامج مسجّلة"
    description="لم تسجّل في أي برنامج تدريبي بعد. تصفّح البرامج المنشورة وسجّل عند توفر مقعد."
>
    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف البرامج</a>
    <a href="{{ route('portal.paths') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">مساراتي</a>
</x-portal.empty-state>
@else
<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="overflow-x-auto">
    <table class="w-full min-w-[720px] text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
                <th class="px-5 py-3 text-right font-medium">البرنامج</th>
                <th class="px-5 py-3 text-center font-medium">وقت البرنامج</th>
                <th class="px-5 py-3 text-center font-medium">الحالة</th>
                <th class="px-5 py-3 text-center font-medium">التحضير</th>
                <th class="px-5 py-3 text-center font-medium">نسبة الحضور</th>
                <th class="px-5 py-3 text-center font-medium">الدرجة</th>
                <th class="px-5 py-3 text-center font-medium">أهلية الشهادة</th>
                <th class="px-5 py-3 text-center font-medium">مجموعة الواتساب</th>
                <th class="px-5 py-3 text-center font-medium">الشهادة</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
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
            @endphp
            <tr class="transition hover:bg-gray-50">
                <td class="px-5 py-4 font-medium text-gray-800">
                    @if ($programShowUrl)
                    <a href="{{ $programShowUrl }}" class="transition hover:text-[#335483] hover:underline">
                        {{ $program->title }}
                    </a>
                    @else
                    —
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($programShowUrl && $timingLabel)
                    <a href="{{ $programShowUrl }}" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold transition hover:opacity-90
                        @if (str_starts_with($timingLabel, 'متبق')) bg-[#e9eff6] text-[#335483]
                        @elseif ($timingLabel === 'جار') bg-emerald-50 text-emerald-700
                        @else bg-slate-100 text-slate-600
                        @endif">
                        {{ $timingLabel }}
                    </a>
                    @elseif ($timingLabel)
                    <span class="text-xs text-gray-500">{{ $timingLabel }}</span>
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($isAccepted && $checkInUrl)
                    <a
                        href="{{ $checkInUrl }}"
                        class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                        style="background:#335483"
                    >
                        @if ($isInPerson)
                            QR الحضور
                        @else
                            التحضير
                        @endif
                    </a>
                    @elseif ($checkInUrl)
                    <span class="text-xs text-gray-400">بعد القبول</span>
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center text-gray-600">
                    @if ($reg->attendance_percentage !== null)
                    {{ number_format((float) $reg->attendance_percentage, 1) }}%
                    @else
                    —
                    @endif
                </td>
                <td class="px-5 py-4 text-center text-gray-600">
                    {{ $reg->score !== null ? number_format((float) $reg->score, 1) : '—' }}
                </td>
                <td class="px-5 py-4 text-center">
                    @php
                    $showElig = in_array($reg->status->value, [
                    \App\Enums\RegistrationStatus::Approved->value,
                    \App\Enums\RegistrationStatus::Completed->value,
                    ]);
                    $attOk = $reg->attendance_percentage !== null && (float)$reg->attendance_percentage >= 80;
                    $scoreOk = $reg->score === null || (float)$reg->score >= 60;
                    @endphp
                    @if ($showElig && $reg->attendance_percentage !== null)
                    @if ($attOk && $scoreOk)
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ config('brand.classes.badge_secondary') }}">مؤهل ✓</span>
                    @else
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ config('brand.classes.badge_danger') }}">غير مؤهل حتى الآن</span>
                    @endif
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($whatsappUrl)
                        <a
                            href="{{ $whatsappUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                            style="background:#25D366"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            انضم
                        </a>
                    @elseif ($isAccepted)
                        <span class="text-xs text-gray-400">—</span>
                    @else
                        <span class="text-xs text-gray-400">بعد القبول</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($reg->certificate)
                    @if ($reg->certificate->downloadUrl())
                    <a href="{{ $reg->certificate->downloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-block rounded-lg bg-brand px-3 py-1 text-xs font-medium text-white transition hover:opacity-95">
                        تحميل
                    </a>
                    @else
                    <span class="text-xs font-medium text-brand">صادرة</span>
                    @endif
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    @if ($registrations->hasPages())
    <div class="border-t border-gray-100 px-5 py-4">
        {{ $registrations->links() }}
    </div>
    @endif
</div>
@endif
@endsection
