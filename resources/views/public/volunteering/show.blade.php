@php
// التسجيل في الفرص التطوعية يتم عبر المنصة الوطنية للتطوع.
$externalUrl = $volunteerOpportunity->hasExternalUrl() ? $volunteerOpportunity->external_url : null;
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
            @if ($externalUrl)
                <p class="text-sm leading-relaxed text-gray-500 sm:max-w-md">يتم التسجيل في هذه الفرصة عبر المنصة الوطنية للعمل التطوعي.</p>
                <x-public.register-cta-button :href="$externalUrl" target="_blank" rel="noopener noreferrer" class="hidden md:inline-flex">سجّل في الفرصة</x-public.register-cta-button>
            @else
                <p class="text-sm text-gray-500">سيُتاح رابط التسجيل في هذه الفرصة قريباً.</p>
            @endif
        </div>
    </x-slot:action>

    <x-slot:mobileStickyAction>
        @if ($externalUrl)
            <x-public.register-cta-button :href="$externalUrl" target="_blank" rel="noopener noreferrer" class="w-full">سجّل في الفرصة</x-public.register-cta-button>
        @endif
    </x-slot:mobileStickyAction>
</x-public.entity-show-layout>

@endsection