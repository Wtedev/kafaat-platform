@php
use App\Enums\RegistrationStatus;

$statusLabels = [
RegistrationStatus::Pending->value => 'قيد المراجعة',
RegistrationStatus::Approved->value => 'مقبول',
RegistrationStatus::Rejected->value => 'مرفوض',
RegistrationStatus::Cancelled->value => 'ملغي',
RegistrationStatus::Completed->value => 'مكتمل',
];
$statusColors = [
RegistrationStatus::Pending->value => 'bg-yellow-100 text-yellow-700',
RegistrationStatus::Approved->value => 'bg-green-100 text-green-700',
RegistrationStatus::Rejected->value => 'bg-red-100 text-red-700',
RegistrationStatus::Cancelled->value => 'bg-gray-100 text-gray-600',
RegistrationStatus::Completed->value => 'bg-blue-100 text-blue-700',
];

$canRegister = auth()->check()
&& auth()->user()->role_type === 'beneficiary'
&& $userRegistration === null;

$alreadyRegistered = $userRegistration !== null;
@endphp

@extends('layouts.public')
@section('title', $volunteerOpportunity->title)
@section('content')

<div class="mb-4">
    <a href="{{ route('public.volunteering.index') }}" class="text-sm text-indigo-600 hover:underline">← الفرص التطوعية</a>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">{{ $volunteerOpportunity->title }}</h1>

    <div class="flex flex-wrap gap-4 text-sm text-gray-500 mb-5">
        @if ($volunteerOpportunity->hours_expected)
        <span>⏱ الساعات المطلوبة: {{ number_format((float)$volunteerOpportunity->hours_expected, 0) }}</span>
        @endif
        @if ($volunteerOpportunity->capacity)
        <span>👥 الطاقة: {{ $volunteerOpportunity->capacity }}</span>
        @endif
        @if ($volunteerOpportunity->start_date)
        <span>📅 البداية: {{ $volunteerOpportunity->start_date->format('Y/m/d') }}</span>
        @endif
        @if ($volunteerOpportunity->end_date)
        <span>🏁 النهاية: {{ $volunteerOpportunity->end_date->format('Y/m/d') }}</span>
        @endif
    </div>

    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $volunteerOpportunity->description }}</p>
</div>

{{-- Registration block --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    @if ($alreadyRegistered)
    @php $sv = $userRegistration->status->value; @endphp
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $statusLabels[$sv] ?? $sv }}
        </span>
        <span class="text-sm text-gray-500">لقد سجّلت في هذه الفرصة التطوعية بالفعل.</span>
    </div>
    @elseif ($canRegister)
    <form method="POST" action="{{ route('public.volunteering.register', $volunteerOpportunity->slug) }}">
        @csrf
        <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition shadow-sm">
            سجّل الآن
        </button>
    </form>
    @elseif (! auth()->check())
    <a href="{{ route('login') }}" class="inline-block px-6 py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition shadow-sm">
        سجّل الدخول للتسجيل
    </a>
    @else
    <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
    @endif
</div>

@endsection
