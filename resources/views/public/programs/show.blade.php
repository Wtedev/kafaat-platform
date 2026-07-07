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

<x-public.entity-show-layout
    :backHref="$trainingProgram->competency_track ? route('public.programs.track', $trainingProgram->competency_track) : route('public.tracks.index')"
    :backLabel="$trainingProgram->competency_track?->shortLabel() ?? 'مسارات الكفاءة'"
    :title="$trainingProgram->title"
    :description="$trainingProgram->description"
    descriptionHeading="نبذة عن البرنامج"
    mediaContext="program"
    :programKind="$trainingProgram->program_kind"
    :hasImage="filled($trainingProgram->image)"
    :imageUrl="$trainingProgram->imagePublicUrl()"
>
    <x-slot:sidebar>
        <x-public.program-info-sidebar :trainingProgram="$trainingProgram" />
    </x-slot:sidebar>

    <x-slot:action>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if ($alreadyRegistered)
                @php $sv = $userRegistration->status->value; @endphp
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
                    <span class="text-sm text-gray-500">لقد سجّلت في هذا البرنامج بالفعل.</span>
                </div>
            @elseif ($viaPathOnly)
                <div class="space-y-3 sm:max-w-xl">
                    <p class="text-sm leading-relaxed text-gray-600">
                        هذا البرنامج جزء من مسار تعليمي. التسجيل يتم عبر المسار فقط، وبعد قبولك في المسار تُسجَّل تلقائياً في جميع برامجه.
                    </p>
                    @if ($trainingProgram->learningPath)
                        <x-public.register-cta-button :href="route('public.paths.show', $trainingProgram->learningPath->slug)">
                            الانتقال إلى صفحة المسار
                        </x-public.register-cta-button>
                    @endif
                </div>
            @elseif (! $trainingProgram->isRegistrationOpen())
                <p class="text-sm text-gray-400">باب التسجيل في هذا البرنامج مغلق حالياً.</p>
            @elseif ($canRegister)
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">سجّل الآن للانضمام إلى هذا البرنامج التدريبي.</p>
                <form method="POST" action="{{ route('public.programs.register', $trainingProgram->slug) }}" class="shrink-0">
                    @csrf
                    <x-public.register-cta-button type="submit">سجّل في البرنامج</x-public.register-cta-button>
                </form>
            @elseif (! auth()->check())
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">يجب تسجيل الدخول كمستفيد للتسجيل في البرنامج.</p>
                <x-public.register-cta-button :href="route('login')">سجّل الدخول للتسجيل</x-public.register-cta-button>
            @else
                <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
            @endif
        </div>
    </x-slot:action>
</x-public.entity-show-layout>

@endsection
