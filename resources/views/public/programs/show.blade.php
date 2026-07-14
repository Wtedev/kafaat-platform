@php
use App\Enums\ProgramDeliveryMode;
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
$inPerson = $trainingProgram->delivery_mode === ProgramDeliveryMode::InPerson;
$venueHint = filled($trainingProgram->venue) ? $trainingProgram->venue : 'مدينة وموقع الانعقاد';

$canRegister = auth()->check()
&& auth()->user()->canRegisterForPublicOfferings()
&& $userRegistration === null
&& ! $viaPathOnly
&& $trainingProgram->isRegistrationOpen();

$ineligible = is_array($acceptanceEvaluation ?? null)
&& ($acceptanceEvaluation['eligible'] ?? true) === false;
$ineligibilityReasons = $ineligible
? ($acceptanceEvaluation['reasons'] ?? [])
: [];

$alreadyRegistered = $userRegistration !== null;
$ackLabel = $inPerson
? 'أقر بأنني قرأت جميع تفاصيل البرنامج وأعرف مدينة وموقع إقامته ('.$venueHint.') وأستطيع الحضور.'
: 'أقر بأنني قرأت جميع تفاصيل البرنامج وأستطيع الالتزام بمواعيده.';
@endphp

@extends('layouts.public')
@section('title', $trainingProgram->title)
@section('content')

<x-public.entity-show-layout :backHref="$trainingProgram->competency_track ? route('public.programs.track', $trainingProgram->competency_track) : route('public.tracks.index')" :backLabel="$trainingProgram->competency_track?->shortLabel() ?? 'مسارات الكفاءة'" :title="$trainingProgram->title" :description="$trainingProgram->description" descriptionHeading="نبذة عن البرنامج" mediaContext="program" :programKind="$trainingProgram->program_kind" :hasImage="filled($trainingProgram->image)" :imageUrl="$trainingProgram->imagePublicUrl()" objectFit="cover">
    <x-slot:mediaBadges>
        <span class="inline-flex items-center rounded-lg bg-white/95 px-2.5 py-1 text-xs font-medium text-[#335483] shadow-sm ring-1 ring-white/60 backdrop-blur-sm">
            {{ $trainingProgram->program_kind->label() }}
        </span>
        @if ($trainingProgram->competency_track)
        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-gray-900 shadow-sm ring-1 ring-black/5" style="background:#FCB420">
            {{ $trainingProgram->competency_track->shortLabel() }}
        </span>
        @endif
    </x-slot:mediaBadges>

    <x-slot:afterDescription>
        <x-public.program-session-topics
            :enabled="(bool) $trainingProgram->session_topics_enabled"
            :topics="$trainingProgram->session_topics"
            @class([
                'mt-8 border-t border-[#c5d4e4]/70 pt-8' => filled($trainingProgram->description),
                'mt-0 border-0 pt-0' => blank($trainingProgram->description),
            ])
        />
        <x-public.program-presenters
            :presenters="$trainingProgram->program_presenters"
            @class([
                'mt-8 border-t border-[#c5d4e4]/70 pt-8' => filled($trainingProgram->description)
                    || ((bool) $trainingProgram->session_topics_enabled && filled(\App\Support\TrainingProgramExtrasSupport::publicSessionTopics($trainingProgram))),
                'mt-0 border-0 pt-0' => blank($trainingProgram->description)
                    && ! ((bool) $trainingProgram->session_topics_enabled && filled(\App\Support\TrainingProgramExtrasSupport::publicSessionTopics($trainingProgram))),
            ])
        />
    </x-slot:afterDescription>

    <x-slot:sidebar>
        <x-public.program-info-sidebar :trainingProgram="$trainingProgram" />
    </x-slot:sidebar>

    <x-slot:action>
        <div class="flex flex-col gap-4">
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
                @auth
                @if (auth()->user()->canRegisterForPublicOfferings())
                <x-public.register-cta-button :href="route('portal.paths')" class="hidden md:inline-flex">
                    الانتقال إلى مساراتي
                </x-public.register-cta-button>
                @endif
                @else
                <x-public.register-cta-button :href="route('login')" class="hidden md:inline-flex">
                    سجّل الدخول للانضمام للمسار
                </x-public.register-cta-button>
                @endauth
            </div>
            @elseif (! $trainingProgram->isRegistrationOpen())
            <p class="text-sm text-gray-400">باب التسجيل في هذا البرنامج مغلق حالياً.</p>
            @elseif ($ineligible)
            <div class="space-y-3 rounded-2xl border border-amber-200/80 bg-amber-50/80 p-4 sm:max-w-xl">
                <p class="text-sm font-medium text-amber-900">غير مؤهل للتسجيل في هذا البرنامج</p>
                <ul class="list-disc space-y-1 pe-5 text-sm leading-relaxed text-amber-800">
                    @foreach ($ineligibilityReasons as $reason)
                    <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
            @elseif ($canRegister)
            <form method="POST" action="{{ route('public.programs.register', $trainingProgram->slug) }}" id="program-register-form" class="program-register-form space-y-4">
                @csrf
                <div>
                    <p class="text-sm font-medium text-gray-800">سجّل الآن للانضمام إلى هذا البرنامج</p>
                    <p class="mt-1 text-sm leading-relaxed text-gray-500">قبل الإرسال، أكّد اطلاعك على التفاصيل أدناه.</p>
                </div>

                <label class="program-register-ack flex cursor-pointer items-start gap-3 rounded-2xl border border-[#c5d4e4]/80 bg-[#F7FAFC] p-4 transition hover:border-[#335483]/40">
                    <input type="checkbox" name="attendance_acknowledgement" value="1" required class="mt-1 size-4 shrink-0 rounded border-gray-300 text-[#335483] focus:ring-[#335483]" @checked(old('attendance_acknowledgement'))>
                    <span class="text-sm leading-relaxed text-gray-700">{{ $ackLabel }}</span>
                </label>
                @error('attendance_acknowledgement')
                <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-400">لن يكتمل التسجيل دون هذا الإقرار.</p>
                    <x-public.register-cta-button type="submit" class="hidden md:inline-flex">سجّل في البرنامج</x-public.register-cta-button>
                </div>
            </form>
            @elseif (! auth()->check())
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">يجب تسجيل الدخول للتسجيل في البرنامج.</p>
                <x-public.register-cta-button :href="route('login')" class="hidden md:inline-flex">سجّل الدخول للتسجيل</x-public.register-cta-button>
            </div>
            @else
            <p class="text-sm text-gray-400">لا يمكن التسجيل بهذا الحساب حالياً.</p>
            @endif
        </div>
    </x-slot:action>

    <x-slot:mobileStickyAction>
        @if ($canRegister)
            <x-public.register-cta-button type="submit" form="program-register-form" class="w-full">سجّل في البرنامج</x-public.register-cta-button>
        @elseif ($viaPathOnly)
            @auth
                @if (auth()->user()->canRegisterForPublicOfferings())
                    <x-public.register-cta-button :href="route('portal.paths')" class="w-full">الانتقال إلى مساراتي</x-public.register-cta-button>
                @endif
            @else
                <x-public.register-cta-button :href="route('login')" class="w-full">سجّل الدخول للانضمام للمسار</x-public.register-cta-button>
            @endauth
        @elseif (! auth()->check() && $trainingProgram->isRegistrationOpen() && ! $viaPathOnly)
            <x-public.register-cta-button :href="route('login')" class="w-full">سجّل الدخول للتسجيل</x-public.register-cta-button>
        @endif
    </x-slot:mobileStickyAction>
</x-public.entity-show-layout>

@endsection
