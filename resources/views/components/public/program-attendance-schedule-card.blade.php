@props(['trainingProgram'])

@php
use App\Support\VolunteerLeadersProgramPeriod;

$programDateRange = null;
if ($trainingProgram->start_date && $trainingProgram->end_date) {
    $programDateRange = ar_date($trainingProgram->start_date, 'd MMM').' – '.ar_date($trainingProgram->end_date, 'd MMM');
} elseif ($trainingProgram->start_date) {
    $programDateRange = ar_date($trainingProgram->start_date, 'd MMM');
} elseif ($trainingProgram->end_date) {
    $programDateRange = ar_date($trainingProgram->end_date, 'd MMM');
}

$registrationStatusLabel = $trainingProgram->scheduleCardRegistrationStatusLabel();
@endphp

@if (VolunteerLeadersProgramPeriod::applies($trainingProgram))
<aside {{ $attributes->class(['overflow-hidden rounded-2xl bg-white']) }}>
    <div class="flex items-center gap-3 border-b border-[#e9eff6] bg-[#e9eff6]/80 px-5 py-3.5">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </span>
        <h2 class="text-sm font-bold text-[#335483]">مواعيد البرنامج</h2>
    </div>

    <div class="space-y-0 divide-y divide-[#dce5ef] px-5 pb-5 pt-1">
        @if ($trainingProgram->delivery_mode)
        <x-public.info-sidebar-item dense label="أسلوب التنفيذ" :value="$trainingProgram->delivery_mode->label()">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
        @endif

        @if ($programDateRange)
        <x-public.info-sidebar-item dense label="فترة البرنامج" :value="en_digits($programDateRange)">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
        @endif

        <x-public.info-sidebar-item dense label="الأيام الحضورية" :value="VolunteerLeadersProgramPeriod::inPersonDaysLabel()">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>

        <x-public.info-sidebar-item dense label="الأيام عن بعد" :value="VolunteerLeadersProgramPeriod::remoteDaysLabel()">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>

        <x-public.info-sidebar-item dense label="حالة التسجيل" :value="$registrationStatusLabel">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
    </div>
</aside>
@endif
