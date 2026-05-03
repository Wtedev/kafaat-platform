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

$viaPathOnly = $trainingProgram->learning_path_id !== null;

$canRegister = auth()->check()
&& auth()->user()->isPortalUser()
&& $userRegistration === null
&& ! $viaPathOnly
&& $trainingProgram->isRegistrationOpen();

$alreadyRegistered = $userRegistration !== null;
@endphp

@extends('layouts.public')
@section('title', $trainingProgram->title)
@section('content')

<div class="mb-4">
    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity" style="color:#253B5B">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        البرامج التدريبية
    </a>
</div>

<h1 class="text-2xl font-bold text-gray-900 mb-4 sm:text-3xl">{{ $trainingProgram->title }}</h1>

<x-public.card-media
    variant="hero"
    mediaContext="program"
    :programKind="$trainingProgram->program_kind"
    :hasImage="filled($trainingProgram->image)"
    :imageUrl="$trainingProgram->imagePublicUrl()"
    :alt="$trainingProgram->title"
/>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">

    <div class="flex flex-wrap gap-4 text-sm text-gray-500 mb-5">
        @if ($trainingProgram->start_date)
        <span>📅 البداية: {{ $trainingProgram->start_date->format('Y/m/d') }}</span>
        @endif
        @if ($trainingProgram->end_date)
        <span>🏁 النهاية: {{ $trainingProgram->end_date->format('Y/m/d') }}</span>
        @endif
        @if ($trainingProgram->capacity)
        <span>👥 الطاقة: {{ $trainingProgram->capacity }}</span>
        @endif
        @if ($trainingProgram->registration_start && $trainingProgram->registration_end)
        <span>
            📋 التسجيل: {{ $trainingProgram->registration_start->format('Y/m/d') }}
            — {{ $trainingProgram->registration_end->format('Y/m/d') }}
        </span>
        @endif
    </div>

    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $trainingProgram->description }}</p>
</div>

{{-- Registration block --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    @if ($alreadyRegistered)
    @php $sv = $userRegistration->status->value; @endphp
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $statusLabels[$sv] ?? $sv }}
        </span>
        <span class="text-sm text-gray-500">لقد سجّلت في هذا البرنامج بالفعل.</span>
    </div>
    @elseif ($viaPathOnly)
    <p class="text-sm text-gray-600 mb-3">
        هذا البرنامج جزء من مسار تعليمي. التسجيل يتم عبر المسار فقط، وبعد قبولك في المسار تُسجَّل تلقائياً في جميع برامجه.
    </p>
    @if ($trainingProgram->learningPath)
    <a href="{{ route('public.paths.show', $trainingProgram->learningPath->slug) }}" class="inline-block px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
        الانتقال إلى صفحة المسار
    </a>
    @endif
    @elseif (! $trainingProgram->isRegistrationOpen())
    <p class="text-sm text-gray-400">باب التسجيل في هذا البرنامج مغلق حالياً.</p>
    @elseif ($canRegister)
    <form method="POST" action="{{ route('public.programs.register', $trainingProgram->slug) }}">
        @csrf
        <button type="submit" class="px-6 py-3 rounded-2xl text-sm font-semibold text-white shadow-sm hover:shadow-md
                       transition-all duration-200 hover:-translate-y-0.5" style="background:#253B5B">
            سجّل في البرنامج
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
