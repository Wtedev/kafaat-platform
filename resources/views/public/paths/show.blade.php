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

$canRegisterPath = auth()->check()
&& auth()->user()->isPortalUser()
&& $userRegistration === null;

$alreadyRegisteredPath = $userRegistration !== null;
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

<h1 class="mb-3 text-2xl font-bold text-gray-900 sm:text-3xl">{{ $learningPath->title }}</h1>

<x-public.card-media
    variant="hero"
    mediaContext="path"
    :hasImage="filled($learningPath->image)"
    :imageUrl="$learningPath->imagePublicUrl()"
    :alt="$learningPath->title"
/>

<div class="mb-6 rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
    @if ($learningPath->capacity)
    <p class="mb-4 text-sm text-gray-500">👥 الطاقة الاستيعابية: {{ $learningPath->capacity }}</p>
    @endif
    <p class="leading-relaxed whitespace-pre-line text-gray-600">{{ $learningPath->description }}</p>
</div>

@if ($learningPath->programs->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-700 mb-2">البرامج في المسار</h2>
    <p class="text-xs text-gray-500 mb-4">التسجيل يتم من قسم المسار أدناه؛ عند قبولك يُفعَّل تسجيلك في جميع هذه البرامج تلقائياً.</p>
    <ul class="space-y-4">
        @foreach ($learningPath->programs as $program)
        @php
        $userProgReg = auth()->check() ? $program->registrations->first() : null;
        $regLabel = $userProgReg ? ($statusLabels[$userProgReg->status->value] ?? $userProgReg->status->value) : null;
        $regColor = $userProgReg ? ($statusColors[$userProgReg->status->value] ?? 'bg-gray-100 text-gray-600') : null;
        @endphp
        <li class="flex flex-wrap items-stretch gap-4 rounded-xl border border-gray-100 p-4">
            <x-public.card-media
                variant="thumb"
                mediaContext="program"
                :programKind="$program->program_kind"
                :hasImage="filled($program->image)"
                :imageUrl="$program->imagePublicUrl()"
                :alt="$program->title"
                :index="$loop->index"
            />
            <div class="flex min-w-0 flex-1 flex-col justify-between gap-3 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold" style="background:#EAF2FA; color:#253B5B">
                            {{ $program->program_kind->label() }}
                        </span>
                        @if ($userProgReg)
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $regColor }}">{{ $regLabel }}</span>
                        @endif
                    </div>
                    <p class="font-medium text-gray-900">{{ $program->title }}</p>
                </div>
                <div class="shrink-0 self-end sm:self-start">
                    <a href="{{ route('public.programs.show', $program) }}" class="inline-block rounded-xl px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                        التفاصيل
                    </a>
                </div>
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    @if ($alreadyRegisteredPath)
    @php $sv = $userRegistration->status->value; @endphp
    <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $statusLabels[$sv] ?? $sv }}
        </span>
        <span class="text-sm text-gray-500">لقد سجّلت في هذا المسار بالفعل.</span>
    </div>
    @elseif ($canRegisterPath)
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
