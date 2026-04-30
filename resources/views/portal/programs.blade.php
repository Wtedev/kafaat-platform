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
@section('title', 'برامجي التدريبية')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">برامجي التدريبية</h1>

@if ($registrations->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لم تسجّل في أي برنامج تدريبي بعد.
</div>
@else
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="px-5 py-3 text-right font-medium">البرنامج</th>
                <th class="px-5 py-3 text-center font-medium">الحالة</th>
                <th class="px-5 py-3 text-center font-medium">نسبة الحضور</th>
                <th class="px-5 py-3 text-center font-medium">الدرجة</th>
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
                    @if ($reg->certificate)
                    @if ($reg->certificate->fileUrl())
                    <a href="{{ $reg->certificate->fileUrl() }}" target="_blank" class="inline-block px-3 py-1 rounded-lg text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition">
                        تحميل
                    </a>
                    @else
                    <span class="text-xs text-blue-600 font-medium">صادرة</span>
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
