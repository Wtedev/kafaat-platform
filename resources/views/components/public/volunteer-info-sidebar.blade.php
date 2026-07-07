@props(['volunteerOpportunity'])

<x-public.info-sidebar title="معلومات الفرصة">
    @if ($volunteerOpportunity->hours_expected)
        <x-public.info-sidebar-item label="الساعات المطلوبة" :value="en_num((float) $volunteerOpportunity->hours_expected, 0)">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
    @endif

    @if ($volunteerOpportunity->capacity)
        <x-public.info-sidebar-item label="الطاقة" :value="en_num($volunteerOpportunity->capacity)">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
    @endif

    @if ($volunteerOpportunity->start_date)
        <x-public.info-sidebar-item label="البداية" :value="$volunteerOpportunity->start_date->format('Y/m/d')">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
    @endif

    @if ($volunteerOpportunity->end_date)
        <x-public.info-sidebar-item label="النهاية" :value="$volunteerOpportunity->end_date->format('Y/m/d')">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </x-slot:icon>
        </x-public.info-sidebar-item>
    @endif
</x-public.info-sidebar>
