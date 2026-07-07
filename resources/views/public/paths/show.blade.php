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

<x-public.entity-show-layout
    :backHref="route('public.paths.index')"
    backLabel="المسارات التدريبية"
    :title="$learningPath->title"
    :description="$learningPath->description"
    descriptionHeading="نبذة عن المسار"
    mediaContext="path"
    :hasImage="filled($learningPath->image)"
    :imageUrl="$learningPath->imagePublicUrl()"
>
    <x-slot:sidebar>
        <x-public.path-info-sidebar :learningPath="$learningPath" />
    </x-slot:sidebar>

    @if ($learningPath->programs->isNotEmpty())
        <x-slot:extra>
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
        </x-slot:extra>
    @endif

    <x-slot:action>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if ($alreadyRegisteredPath)
                @php $sv = $userRegistration->status->value; @endphp
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
                    <span class="text-sm text-gray-500">لقد سجّلت في هذا المسار بالفعل.</span>
                </div>
            @elseif ($canRegisterPath)
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">سجّل في المسار للانضمام إلى جميع برامجه التدريبية.</p>
                <form method="POST" action="{{ route('public.paths.register', $learningPath->slug) }}" class="shrink-0">
                    @csrf
                    <x-public.register-cta-button type="submit">سجّل في المسار</x-public.register-cta-button>
                </form>
            @elseif (! auth()->check())
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">يجب تسجيل الدخول كمستفيد للتسجيل في المسار.</p>
                <x-public.register-cta-button :href="route('login')">سجّل الدخول للتسجيل</x-public.register-cta-button>
            @else
                <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
            @endif
        </div>
    </x-slot:action>
</x-public.entity-show-layout>

@endsection
