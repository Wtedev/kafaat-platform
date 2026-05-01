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
    <div
        role="group"
        aria-label="نوع المستفيد"
        class="flex flex-wrap items-center gap-2 {{ $justify }}"
    >
        @foreach ($p->displayMembershipBadges() as $label)
        <span class="inline-flex max-w-full rounded-full px-2.5 py-0.5 text-xs font-semibold leading-tight {{ $p->membershipBadgeClasses($label) }}">
            {{ $label }}
        </span>
        @endforeach
    </div>
    <div class="text-center">
        <div class="mb-2 flex items-center gap-2.5">
            <span class="h-px min-w-0 flex-1 bg-slate-200/90" aria-hidden="true"></span>
            <span class="shrink-0 text-[11px] font-bold text-slate-500">المهارة الأيقونية</span>
            <span class="h-px min-w-0 flex-1 bg-slate-200/90" aria-hidden="true"></span>
        </div>
        <div class="flex {{ $justify }}">
            <span
                class="inline-flex w-full max-w-[17.5rem] items-center gap-2.5 rounded-xl bg-gradient-to-br px-3 py-2.5 text-sm font-semibold leading-snug {{ $p->iconicSkillGradientClasses() }} {{ $p->iconicSkillClasses() }}"
                aria-label="المهارة الأيقونية: {{ $p->iconicSkillLabel() }}"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/75 ring-1 ring-black/[0.05]">
                    <svg class="h-4 w-4 shrink-0 text-current opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $skillIcon }}"/></svg>
                </span>
                <span class="min-w-0 flex-1 text-right leading-snug">{{ $p->iconicSkillLabel() }}</span>
            </span>
        </div>
    </div>
</div>
@endif
