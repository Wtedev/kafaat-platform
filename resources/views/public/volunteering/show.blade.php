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

<x-public.entity-show-layout
    :backHref="route('public.volunteering.index')"
    backLabel="الفرص التطوعية"
    :title="$volunteerOpportunity->title"
    :description="$volunteerOpportunity->description"
    descriptionHeading="نبذة عن الفرصة"
    mediaContext="volunteer"
    :hasImage="filled($volunteerOpportunity->image)"
    :imageUrl="$volunteerOpportunity->imagePublicUrl()"
>
    <x-slot:sidebar>
        <x-public.volunteer-info-sidebar :volunteerOpportunity="$volunteerOpportunity" />
    </x-slot:sidebar>

    <x-slot:action>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            @if ($alreadyRegistered)
                @php $sv = $userRegistration->status->value; @endphp
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
                    <span class="text-sm text-gray-500">لقد سجّلت في هذه الفرصة التطوعية بالفعل.</span>
                </div>
            @elseif ($canRegister)
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">سجّل طلبك الآن للمشاركة في هذه الفرصة التطوعية.</p>
                <form method="POST" action="{{ route('public.volunteering.register', $volunteerOpportunity->slug) }}" class="shrink-0">
                    @csrf
                    <x-public.register-cta-button type="submit">قدّم طلبك</x-public.register-cta-button>
                </form>
            @elseif (! auth()->check())
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">يجب تسجيل الدخول كمستفيد لتقديم طلب التطوع.</p>
                <x-public.register-cta-button :href="route('login')">سجّل الدخول للتسجيل</x-public.register-cta-button>
            @else
                <p class="text-sm text-gray-400">التسجيل متاح للمستفيدين فقط.</p>
            @endif
        </div>
    </x-slot:action>
</x-public.entity-show-layout>

@endsection
