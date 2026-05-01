@props([
    'profile' => null,
    /** @var 'center'|'end' $align */
    'align' => 'center',
])

@php
$p = $profile;
$justify = $align === 'end' ? 'justify-center sm:justify-end' : 'justify-center';
$skillIcon = 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z';
@endphp

@if ($p)
<div {{ $attributes->class('flex w-full flex-col gap-3') }}>
    <div>
        <p class="mb-1.5 text-[11px] font-medium text-gray-500">نوع المستفيد</p>
        <div
            role="group"
            aria-label="نوع المستفيد"
            class="flex flex-wrap items-center gap-2 {{ $justify }}"
        >
            @foreach ($p->displayMembershipBadges() as $label)
            <span class="inline-flex max-w-full rounded-full px-2.5 py-0.5 text-[11px] font-semibold leading-tight shadow-sm {{ $p->membershipBadgeClasses($label) }}">
                {{ $label }}
            </span>
            @endforeach
        </div>
    </div>
    <div>
        <p class="mb-1.5 text-[11px] font-medium text-gray-500">المهارة الأيقونية</p>
        <div class="flex {{ $justify }}">
            <span
                class="inline-flex max-w-full items-center gap-1 rounded-full px-3 py-1 text-[11px] font-semibold shadow-sm {{ $p->iconicSkillClasses() }}"
                aria-label="المهارة الأيقونية"
            >
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $skillIcon }}"/></svg>
                <span class="truncate">{{ $p->iconicSkillLabel() }}</span>
            </span>
        </div>
    </div>
</div>
@endif
