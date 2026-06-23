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

$canRegister = auth()->check()
&& auth()->user()->isPortalUser()
&& $userRegistration === null;

$alreadyRegistered = $userRegistration !== null;
@endphp

@extends('layouts.public')
@section('title', $volunteerOpportunity->title)
@section('content')

<div class="mb-4">
    <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity" style="color:#335483">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        الفرص التطوعية
    </a>
</div>

<h1 class="text-2xl font-bold text-gray-900 mb-4 sm:text-3xl">{{ $volunteerOpportunity->title }}</h1>

<x-public.card-media
    variant="hero"
    mediaContext="volunteer"
    :hasImage="filled($volunteerOpportunity->image)"
    :imageUrl="$volunteerOpportunity->imagePublicUrl()"
    :alt="$volunteerOpportunity->title"
/>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">

    <div class="flex flex-wrap gap-4 text-sm text-gray-500 mb-5">
        @if ($volunteerOpportunity->hours_expected)
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            الساعات المطلوبة: {{ number_format((float)$volunteerOpportunity->hours_expected, 0) }}
        </span>
        @endif
        @if ($volunteerOpportunity->capacity)
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            الطاقة: {{ $volunteerOpportunity->capacity }}
        </span>
        @endif
        @if ($volunteerOpportunity->start_date)
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            البداية: {{ $volunteerOpportunity->start_date->format('Y/m/d') }}
        </span>
        @endif
        @if ($volunteerOpportunity->end_date)
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            النهاية: {{ $volunteerOpportunity->end_date->format('Y/m/d') }}
        </span>
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
        <button type="submit" class="px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md
                       transition-all duration-200 hover:-translate-y-0.5" style="background:#335483">
            قدّم طلبك
        </button>
    </form>
    @elseif (! auth()->check())
    <a href="{{ route('login') }}" class="inline-block px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm
              hover:shadow-md transition-all duration-200 hover:-translate-y-0.5" style="background:#335483">
        سجّل الدخول للتسجيل
    </a>
    @else
    <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
    @endif
</div>

@endsection
