@php
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
$canCheckIn = in_array($sv, [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value], true)
    && $liveSession !== null
    && $liveSession->isActive();
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

<x-portal-attendance-session
    :status-url="route('portal.programs.attendance.session', $trainingProgram)"
    :check-in-url="route('portal.programs.attendance.check-in', $trainingProgram)"
    :initial-active="$canCheckIn"
    :initial-expires-at-ms="$canCheckIn ? $liveSession->expires_at->getTimestamp() * 1000 : null"
/>

<div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-700 mb-2">نبذة عن البرنامج</h2>
    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $trainingProgram->description ?: '—' }}</p>
</div>

@endsection
