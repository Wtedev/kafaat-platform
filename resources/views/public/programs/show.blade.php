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

$alreadyRegistered = $userRegistration !== null;
$ackLabel = $inPerson
    ? 'أقر بأنني قرأت جميع تفاصيل البرنامج وأعرف مدينة وموقع إقامته ('.$venueHint.') وأستطيع الحضور.'
    : 'أقر بأنني قرأت جميع تفاصيل البرنامج وأستطيع الالتزام بمواعيده.';
@endphp

@extends('layouts.public')
@section('title', $trainingProgram->title)
@section('content')

<x-public.entity-show-layout
    :backHref="$trainingProgram->competency_track ? route('public.programs.track', $trainingProgram->competency_track) : route('public.tracks.index')"
    :backLabel="$trainingProgram->competency_track?->shortLabel() ?? 'مسارات الكفاءة'"
    :title="$trainingProgram->title"
    :description="$trainingProgram->publicDescription()"
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
        <div class="flex flex-col gap-4">
            @if ($alreadyRegistered)
                @php $sv = $userRegistration->status->value; @endphp
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusLabels[$sv] ?? $sv }}
                        </span>
                        <span class="text-sm text-gray-500">لقد سجّلت في هذا البرنامج بالفعل.</span>
                    </div>
                    <a href="{{ route('public.programs.registered', [$trainingProgram->slug, $userRegistration]) }}"
                       class="text-sm font-semibold text-[#335483] hover:underline">
                        عرض صفحة التأكيد
                    </a>
                </div>
            @elseif ($viaPathOnly)
                <div class="space-y-3 sm:max-w-xl">
                    <p class="text-sm leading-relaxed text-gray-600">
                        هذا البرنامج جزء من مسار تعليمي. التسجيل يتم عبر المسار فقط، وبعد قبولك في المسار تُسجَّل تلقائياً في جميع برامجه.
                    </p>
                    @auth
                        @if (auth()->user()->canRegisterForPublicOfferings())
                            <x-public.register-cta-button :href="route('portal.paths')">
                                الانتقال إلى مساراتي
                            </x-public.register-cta-button>
                        @endif
                    @else
                        <x-public.register-cta-button :href="route('login')">
                            سجّل الدخول للانضمام للمسار
                        </x-public.register-cta-button>
                    @endauth
                </div>
            @elseif (! $trainingProgram->isRegistrationOpen())
                <p class="text-sm text-gray-400">باب التسجيل في هذا البرنامج مغلق حالياً.</p>
            @elseif ($canRegister)
                <form method="POST" action="{{ route('public.programs.register', $trainingProgram->slug) }}" class="program-register-form space-y-4">
                    @csrf
                    <div>
                        <p class="text-sm font-semibold text-gray-800">سجّل الآن للانضمام إلى هذا البرنامج</p>
                        <p class="mt-1 text-sm leading-relaxed text-gray-500">قبل الإرسال، أكّد اطلاعك على التفاصيل أدناه.</p>
                    </div>

                    <label class="program-register-ack flex cursor-pointer items-start gap-3 rounded-2xl border border-[#c5d4e4]/80 bg-[#F7FAFC] p-4 transition hover:border-[#335483]/40">
                        <input
                            type="checkbox"
                            name="attendance_acknowledgement"
                            value="1"
                            required
                            class="mt-1 size-4 shrink-0 rounded border-gray-300 text-[#335483] focus:ring-[#335483]"
                            @checked(old('attendance_acknowledgement'))
                        >
                        <span class="text-sm leading-relaxed text-gray-700">{{ $ackLabel }}</span>
                    </label>
                    @error('attendance_acknowledgement')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-gray-400">لن يكتمل التسجيل دون هذا الإقرار.</p>
                        <x-public.register-cta-button type="submit">سجّل في البرنامج</x-public.register-cta-button>
                    </div>
                </form>
            @elseif (! auth()->check())
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">يجب تسجيل الدخول للتسجيل في البرنامج.</p>
                    <x-public.register-cta-button :href="route('login')">سجّل الدخول للتسجيل</x-public.register-cta-button>
                </div>
            @else
                <p class="text-sm text-gray-400">لا يمكن التسجيل بهذا الحساب حالياً.</p>
            @endif
        </div>
    </x-slot:action>
</x-public.entity-show-layout>

@endsection
