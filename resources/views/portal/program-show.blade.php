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
$sv = $registration->status->value;
@endphp

@extends('layouts.portal')
@section('title', $trainingProgram->title)
@section('content')

<div class="mb-6">
    <a href="{{ route('portal.programs') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-80" style="color:#253B5B">
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

<div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-gray-700 mb-2">نبذة عن البرنامج</h2>
    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $trainingProgram->description ?: '—' }}</p>
</div>

@endsection
