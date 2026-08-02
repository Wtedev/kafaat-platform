@props(['trainingProgram'])

@php
use App\Support\VolunteerLeadersProgramPeriod;

$partnerItems = [];
foreach (VolunteerLeadersProgramPeriod::programPartnerGroups() as $group) {
    foreach ($group['partners'] as $partner) {
        $partnerItems[] = [
            'role' => $group['heading'],
            'logo' => $partner['logo'],
            'alt' => $partner['alt'],
        ];
    }
}
@endphp

@if (VolunteerLeadersProgramPeriod::applies($trainingProgram))
<aside {{ $attributes->class(['overflow-hidden rounded-2xl bg-white']) }} aria-labelledby="program-partners-heading">
    <div class="flex items-center gap-3 border-b border-[#e9eff6] bg-[#e9eff6]/80 px-5 py-3.5">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white" aria-hidden="true">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="#335483" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </span>
        <h2 id="program-partners-heading" class="text-sm font-bold text-[#335483]">شركاء البرنامج</h2>
    </div>

    {{-- Single horizontal row only: shrink to fit; scroll on very narrow viewports — never wrap to a second row. --}}
    <div class="overflow-x-auto px-3 py-5 sm:px-4 lg:px-5">
        <ul class="flex w-full min-w-[40rem] flex-nowrap items-start gap-2 sm:min-w-0 sm:gap-2.5 lg:gap-3">
            @foreach ($partnerItems as $partner)
                <li class="flex min-w-0 flex-1 basis-0 flex-col items-center gap-1.5 text-center">
                    <p class="line-clamp-2 w-full text-[9px] font-medium leading-tight text-gray-400 sm:text-[10px] lg:text-[11px]">{{ $partner['role'] }}</p>
                    <div class="flex h-8 w-full items-center justify-center sm:h-9 lg:h-10">
                        <img
                            src="{{ asset($partner['logo']) }}"
                            alt="{{ $partner['alt'] }}"
                            class="max-h-full max-w-full object-contain object-center"
                            loading="lazy"
                        />
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</aside>
@endif
