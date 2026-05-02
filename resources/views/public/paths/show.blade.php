@php
use App\Enums\ProgramStatus;
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

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 mb-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ $learningPath->title }}</h1>

    @if ($learningPath->capacity)
    <p class="text-sm text-gray-400 mb-4">👥 الطاقة الاستيعابية: {{ $learningPath->capacity }}</p>
    @endif

    <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $learningPath->description }}</p>
</div>

@if ($learningPath->programs->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-700 mb-4">البرامج في المسار</h2>
    <ul class="space-y-4">
        @foreach ($learningPath->programs as $program)
        @php
        $userProgReg = auth()->check() ? $program->registrations->first() : null;
        $regLabel = $userProgReg ? ($statusLabels[$userProgReg->status->value] ?? $userProgReg->status->value) : null;
        $regColor = $userProgReg ? ($statusColors[$userProgReg->status->value] ?? 'bg-gray-100 text-gray-600') : null;
        $open = $program->isRegistrationOpen() && $program->status === ProgramStatus::Published;
        @endphp
        <li class="border border-gray-100 rounded-xl p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold" style="background:#EAF2FA; color:#253B5B">
                            {{ $program->program_kind->label() }}
                        </span>
                        @if ($userProgReg)
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $regColor }}">{{ $regLabel }}</span>
                        @endif
                    </div>
                    <p class="font-medium text-gray-900">{{ $program->title }}</p>
                    @if (! $open && $program->status === ProgramStatus::Published)
                    <p class="text-xs text-amber-700 mt-1">باب التسجيل غير مفتوح حالياً لهذا البرنامج.</p>
                    @endif
                </div>
                <div class="flex-shrink-0">
                    @if (auth()->check() && auth()->user()->isPortalUser() && $open && $userProgReg === null)
                    <form method="POST" action="{{ route('public.programs.register', $program->slug) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition" style="background:#253B5B">
                            سجّل في البرنامج
                        </button>
                    </form>
                    @elseif (! auth()->check() && $open)
                    <a href="{{ route('login') }}" class="inline-block px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm" style="background:#253B5B">سجّل الدخول للتسجيل</a>
                    @else
                    <a href="{{ route('public.programs.show', $program) }}" class="inline-block px-4 py-2 rounded-xl text-sm font-semibold ring-1 ring-gray-200 text-gray-700 hover:bg-gray-50">
                        التفاصيل
                    </a>
                    @endif
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
