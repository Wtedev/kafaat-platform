@props(['learningPath'])

@php
$programs = $learningPath->programs;
$programCount = $programs->count();
$starts = $programs->pluck('start_date')->filter();
$ends = $programs->pluck('end_date')->filter();
$earliestStart = $starts->isNotEmpty() ? $starts->min() : null;
$latestEnd = $ends->isNotEmpty() ? $ends->max() : null;
$remaining = $learningPath->remainingCapacity();
$approved = $learningPath->approvedRegistrationsCount();

$weekdayLabels = $programs
    ->map(fn ($program) => $program->weekdaysLabel())
    ->filter()
    ->unique()
    ->values();
@endphp

<x-public.info-sidebar title="معلومات المسار">
    <x-public.info-sidebar-item label="نوع المسار" :value="$learningPath->path_kind->label()">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    <x-public.info-sidebar-item label="عدد البرامج" :value="en_num($programCount).' برنامج'">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    @if ($earliestStart)
    <x-public.info-sidebar-item label="بداية المسار" :value="ar_date($earliestStart)">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($latestEnd)
    <x-public.info-sidebar-item label="نهاية المسار" :value="ar_date($latestEnd)">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($weekdayLabels->isNotEmpty())
    <x-public.info-sidebar-item label="أيام البرامج" :value="$weekdayLabels->implode(' | ')">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    @if ($learningPath->capacity !== null)
    <x-public.info-sidebar-item
        label="السعة الاستيعابية"
        :value="en_num($approved).' / '.en_num($learningPath->capacity).' مقعد'.($remaining !== null ? ' — متبقي '.en_num($remaining) : '')">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @else
    <x-public.info-sidebar-item label="السعة الاستيعابية" value="غير محدودة">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
    @endif

    <x-public.info-sidebar-item
        label="قبول التسجيل"
        :value="$learningPath->auto_accept_registrations ? 'قبول تلقائي' : 'مراجعة يدوية'">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>

    <x-public.info-sidebar-item label="طريقة التسجيل" value="تسجيل واحد يشمل جميع برامج المسار">
        <x-slot:icon>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </x-slot:icon>
    </x-public.info-sidebar-item>
</x-public.info-sidebar>
