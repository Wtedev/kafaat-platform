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
    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity" style="color:#335483">
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

<div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="order-2 min-w-0 space-y-6 lg:order-none lg:col-start-1 lg:row-start-1">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <h2 class="mb-4 text-base font-bold" style="color:#111827">نبذة عن البرنامج</h2>
            <p class="leading-relaxed whitespace-pre-line text-gray-600">{{ $trainingProgram->description }}</p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @if ($alreadyRegistered)
            @php $sv = $userRegistration->status->value; @endphp
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $statusLabels[$sv] ?? $sv }}
                </span>
                <span class="text-sm text-gray-500">لقد سجّلت في هذا البرنامج بالفعل.</span>
            </div>
            @elseif ($viaPathOnly)
            <p class="mb-3 text-sm text-gray-600">
                هذا البرنامج جزء من مسار تعليمي. التسجيل يتم عبر المسار فقط، وبعد قبولك في المسار تُسجَّل تلقائياً في جميع برامجه.
            </p>
            @if ($trainingProgram->learningPath)
            <a href="{{ route('public.paths.show', $trainingProgram->learningPath->slug) }}" class="inline-block rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md" style="background:#335483">
                الانتقال إلى صفحة المسار
            </a>
            @endif
            @elseif (! $trainingProgram->isRegistrationOpen())
            <p class="text-sm text-gray-400">باب التسجيل في هذا البرنامج مغلق حالياً.</p>
            @elseif ($canRegister)
            <form method="POST" action="{{ route('public.programs.register', $trainingProgram->slug) }}">
                @csrf
                <button type="submit" class="rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md" style="background:#335483">
                    سجّل في البرنامج
                </button>
            </form>
            @elseif (! auth()->check())
            <a href="{{ route('login') }}" class="inline-block rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md" style="background:#335483">
                سجّل الدخول للتسجيل
            </a>
            @else
            <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
            @endif
        </div>
    </div>

    <x-public.program-info-sidebar class="order-1 lg:order-none lg:col-start-2 lg:row-start-1" :trainingProgram="$trainingProgram" />
</div>

@endsection
