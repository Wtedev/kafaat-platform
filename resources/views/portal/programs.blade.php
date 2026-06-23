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
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs">
            <tr>
                <th class="px-5 py-3 text-right font-medium">البرنامج</th>
                <th class="px-5 py-3 text-center font-medium">الحالة</th>
                <th class="px-5 py-3 text-center font-medium">نسبة الحضور</th>
                <th class="px-5 py-3 text-center font-medium">الدرجة</th>
                <th class="px-5 py-3 text-center font-medium">أهلية الشهادة</th>
                <th class="px-5 py-3 text-center font-medium">الشهادة</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach ($registrations as $reg)
            @php $sv = $reg->status->value; @endphp
            <tr class="hover:bg-gray-50 transition">
                <td class="px-5 py-4 font-medium text-gray-800">
                    {{ optional($reg->trainingProgram)->title ?? '—' }}
                </td>
                <td class="px-5 py-4 text-center">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
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
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ config('brand.classes.badge_secondary') }}">مؤهل ✓</span>
                    @else
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ config('brand.classes.badge_danger') }}">غير مؤهل</span>
                    @endif
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($reg->certificate)
                    @if ($reg->certificate->downloadUrl())
                    <a href="{{ $reg->certificate->downloadUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-block px-3 py-1 rounded-lg text-xs font-medium bg-brand text-white hover:opacity-95 transition">
                        تحميل
                    </a>
                    @else
                    <span class="text-xs text-brand font-medium">صادرة</span>
                    @endif
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if ($registrations->hasPages())
    <div class="px-5 py-4 border-t border-gray-100">
        {{ $registrations->links() }}
    </div>
    @endif
</div>
@endif
@endsection
