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
@section('title', $learningPath->title)
@section('content')

<div class="mb-4">
    <a href="{{ route('public.paths.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity" style="color:#253B5B">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        المسارات التدريبية
    </a>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ $learningPath->title }}</h1>

    @if ($learningPath->capacity)
    <p class="text-sm text-gray-400 mb-4">👥 الطاقة الاستيعابية: {{ $learningPath->capacity }}</p>
    @endif

    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $learningPath->description }}</p>
</div>

{{-- Courses list --}}
@if ($learningPath->courses->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-700 mb-4">محتوى المسار</h2>
    <ol class="space-y-2">
        @foreach ($learningPath->courses as $i => $course)
        <li class="flex items-center gap-3 text-sm text-gray-700">
            <span class="flex-none w-7 h-7 rounded-full font-bold text-xs flex items-center justify-center" style="background:#EAF2FA; color:#253B5B">
                {{ $i + 1 }}
            </span>
            {{ $course->title }}
        </li>
        @endforeach
    </ol>
</div>
@endif

{{-- Registration block --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    @if ($alreadyRegistered)
    @php $sv = $userRegistration->status->value; @endphp
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $statusLabels[$sv] ?? $sv }}
        </span>
        <span class="text-sm text-gray-500">لقد سجّلت في هذا المسار بالفعل.</span>
    </div>
    @elseif ($canRegister)
    <form method="POST" action="{{ route('public.paths.register', $learningPath->slug) }}">
        @csrf
        <button type="submit" class="px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md
                       transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
            سجّل في المسار
        </button>
    </form>
    @elseif (! auth()->check())
    <a href="{{ route('login') }}" class="inline-block px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm
              hover:shadow-md transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
        سجّل الدخول للتسجيل
    </a>
    @else
    <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
    @endif
</div>

@endsection
