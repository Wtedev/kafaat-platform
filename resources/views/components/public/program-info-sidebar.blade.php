@props(['trainingProgram'])

@php
$viaPathOnly = $trainingProgram->learning_path_id !== null;
$remaining = $trainingProgram->remainingCapacity();
$approved = $trainingProgram->approvedRegistrationsCount();

$programDateRange = null;
if ($trainingProgram->start_date && $trainingProgram->end_date) {
    $programDateRange = ar_date($trainingProgram->start_date, 'd MMM y').' – '.ar_date($trainingProgram->end_date, 'd MMM y');
} elseif ($trainingProgram->start_date) {
    $programDateRange = ar_date($trainingProgram->start_date, 'd MMM y');
} elseif ($trainingProgram->end_date) {
    $programDateRange = ar_date($trainingProgram->end_date, 'd MMM y');
}

$venueMapUrl = filled($trainingProgram->venue)
    && str_contains((string) $trainingProgram->title, 'قادة التطوع')
    ? 'https://share.google/kqJFTgCRM2b0GT1jO'
    : null;
@endphp

<x-public.info-sidebar title="معلومات البرنامج" dense>
    @if ($trainingProgram->delivery_mode)
    <x-public.info-sidebar-item dense label="أسلوب التنفيذ" :value="$trainingProgram->delivery_mode->label()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    <x-public.info-sidebar-item dense label="الرسوم" value="مجاني">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    <x-public.info-sidebar-item dense label="الجنس" value="ذكور وإناث">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    <x-public.info-sidebar-item dense label="مدة البرنامج" :value="en_digits($trainingProgram->programDurationDescription())">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    @if ($programDateRange)
    <x-public.info-sidebar-item dense label="فترة البرنامج" :value="$programDateRange">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if (! $viaPathOnly && $trainingProgram->capacity !== null)
    <x-public.info-sidebar-item
        dense
        label="السعة الاستيعابية"
        :value="en_num($approved).' / '.en_num($trainingProgram->capacity).' مقعد'.($remaining !== null ? ' — متبقي '.en_num($remaining) : '')">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @elseif ($viaPathOnly)
    <x-public.info-sidebar-item dense label="السعة الاستيعابية" value="تُدار عبر المسار التدريبي">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    <x-public.info-sidebar-item dense label="حالة التسجيل" :value="$trainingProgram->registrationWindowStatusLabel()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    @if ($viaPathOnly && $trainingProgram->learningPath)
    <x-public.info-sidebar-item dense label="المسار التدريبي" :value="$trainingProgram->learningPath->title">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if (filled($trainingProgram->venue))
    <x-public.info-sidebar-item
        dense
        separated
        label="موقع البرنامج"
        :value="$trainingProgram->venue"
        :href="$venueMapUrl"
    >
        <x-slot:icon>
            {{-- Folded map: distinct from the inline “open map” affordance on the venue link --}}
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif
</x-public.info-sidebar>
