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
@section('title', 'لوحة التحكم')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">لوحة التحكم</h1>

{{-- Stats cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @php
    $stats = [
    ['label' => 'المسارات التعليمية', 'value' => $pathCount, 'color' => 'indigo'],
    ['label' => 'البرامج التدريبية', 'value' => $programCount, 'color' => 'emerald'],
    ['label' => 'الفرص التطوعية', 'value' => $volunteerCount, 'color' => 'amber'],
    ['label' => 'ساعات تطوع معتمدة', 'value' => number_format($approvedHours, 1), 'color' => 'sky'],
    ];
    @endphp

    @foreach ($stats as $stat)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
        <p class="text-3xl font-bold text-{{ $stat['color'] }}-600 mt-1">{{ $stat['value'] }}</p>
    </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-6">

    {{-- Latest certificates --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-700 mb-4">آخر الشهادات</h2>
        @forelse ($certificates as $cert)
        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <div>
                <p class="text-sm font-medium text-gray-800">
                    {{ optional($cert->certificateable)->title ?? '—' }}
                </p>
                <p class="text-xs text-gray-400">{{ $cert->issued_at?->format('Y/m/d') }}</p>
            </div>
            <span class="text-xs font-mono text-gray-400">{{ $cert->certificate_number }}</span>
        </div>
        @empty
        <p class="text-sm text-gray-400">لا توجد شهادات بعد.</p>
        @endforelse

        @if ($certificates->isNotEmpty())
        <a href="{{ route('portal.certificates') }}" class="mt-3 inline-block text-xs text-indigo-600 hover:underline">
            عرض الكل
        </a>
        @endif
    </div>

    {{-- Recent path registrations --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-700 mb-4">آخر تسجيلات المسارات</h2>
        @forelse ($recentPathRegs as $reg)
        @php $sv = $reg->status->value; @endphp
        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <p class="text-sm text-gray-800">{{ optional($reg->learningPath)->title ?? '—' }}</p>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $statusLabels[$sv] ?? $sv }}
            </span>
        </div>
        @empty
        <p class="text-sm text-gray-400">لا يوجد تسجيلات بعد.</p>
        @endforelse
    </div>

    {{-- Recent program registrations --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 lg:col-span-2">
        <h2 class="text-base font-semibold text-gray-700 mb-4">آخر تسجيلات البرامج</h2>
        @forelse ($recentProgramRegs as $reg)
        @php $sv = $reg->status->value; @endphp
        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <p class="text-sm text-gray-800">{{ optional($reg->trainingProgram)->title ?? '—' }}</p>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $statusLabels[$sv] ?? $sv }}
            </span>
        </div>
        @empty
        <p class="text-sm text-gray-400">لا يوجد تسجيلات بعد.</p>
        @endforelse
    </div>

</div>
@endsection
