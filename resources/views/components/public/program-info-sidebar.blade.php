@props(['trainingProgram'])

@php
$viaPathOnly = $trainingProgram->learning_path_id !== null;
$weekdaysLabel = $trainingProgram->weekdaysLabel();
$remaining = $trainingProgram->remainingCapacity();
$approved = $trainingProgram->approvedRegistrationsCount();
@endphp

<x-public.info-sidebar>
    <x-public.info-sidebar-item label="نوع البرنامج" :value="$trainingProgram->program_kind->label()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    @if ($trainingProgram->competency_track)
    <x-public.info-sidebar-item label="مسار الكفاءة" :value="$trainingProgram->competency_track->shortLabel()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($trainingProgram->delivery_mode)
    <x-public.info-sidebar-item label="طريقة التنفيذ" :value="$trainingProgram->deliveryModeDescription()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    <x-public.info-sidebar-item label="مدة البرنامج" :value="en_digits($trainingProgram->programDurationDescription())">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    @if ($trainingProgram->start_date)
    <x-public.info-sidebar-item label="تاريخ البداية" :value="ar_date($trainingProgram->start_date)">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($trainingProgram->end_date)
    <x-public.info-sidebar-item label="تاريخ النهاية" :value="ar_date($trainingProgram->end_date)">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($weekdaysLabel)
    <x-public.info-sidebar-item label="أيام البرنامج" :value="$weekdaysLabel">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if (! $viaPathOnly && $trainingProgram->capacity !== null)
    <x-public.info-sidebar-item
        label="السعة الاستيعابية"
        :value="en_num($approved).' / '.en_num($trainingProgram->capacity).' مقعد'.($remaining !== null ? ' — متبقي '.en_num($remaining) : '')">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @elseif ($viaPathOnly)
    <x-public.info-sidebar-item label="السعة الاستيعابية" value="تُدار عبر المسار التدريبي">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    <x-public.info-sidebar-item label="حالة التسجيل" :value="$trainingProgram->registrationWindowStatusLabel()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    @if (! $viaPathOnly && $trainingProgram->registration_start && $trainingProgram->registration_end)
    <x-public.info-sidebar-item
        label="فترة التسجيل"
        :value="ar_date($trainingProgram->registration_start).' — '.ar_date($trainingProgram->registration_end)">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if (! $viaPathOnly)
    <x-public.info-sidebar-item
        label="قبول التسجيل"
        :value="$trainingProgram->auto_accept_registrations ? 'قبول تلقائي' : 'مراجعة يدوية'">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($viaPathOnly && $trainingProgram->learningPath)
    <x-public.info-sidebar-item label="المسار التدريبي" :value="$trainingProgram->learningPath->title">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif
</x-public.info-sidebar>
