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

$canRegisterPath = auth()->check()
&& auth()->user()->isPortalUser()
&& $userRegistration === null;

$alreadyRegisteredPath = $userRegistration !== null;
@endphp

@extends('layouts.public')
@section('title', $learningPath->title)
@section('content')

<div class="mb-4">
    <a href="{{ route('public.paths.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium hover:opacity-70 transition-opacity" style="color:#335483">
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

<div class="mt-6 grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="order-2 min-w-0 space-y-6 lg:order-1">
        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
            <h2 class="mb-4 text-base font-bold" style="color:#111827">نبذة عن المسار</h2>
            <p class="leading-relaxed whitespace-pre-line text-gray-600">{{ $learningPath->description }}</p>
        </div>

        @if ($learningPath->programs->isNotEmpty())
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-base font-semibold text-gray-700">البرامج في المسار</h2>
            <p class="mb-4 text-xs text-gray-500">التسجيل يتم من قسم المسار أدناه؛ عند قبولك يُفعَّل تسجيلك في جميع هذه البرامج تلقائياً.</p>
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
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold" style="background:#e9eff6; color:#335483">
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

        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @if ($alreadyRegisteredPath)
            @php $sv = $userRegistration->status->value; @endphp
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $statusLabels[$sv] ?? $sv }}
                </span>
                <span class="text-sm text-gray-500">لقد سجّلت في هذا المسار بالفعل.</span>
            </div>
            @elseif ($canRegisterPath)
            <form method="POST" action="{{ route('public.paths.register', $learningPath->slug) }}">
                @csrf
                <button type="submit" class="rounded-2xl px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md" style="background:#335483">
                    سجّل في المسار
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

    <x-public.path-info-sidebar class="order-1 lg:order-2" :learningPath="$learningPath" />
</div>

@endsection
