@props([])

@php
$u = auth()->user();
$p = $u->profile;
$membership = \App\Services\Portal\BeneficiaryMembershipResolver::resolve($u);
$iconic = $p?->iconic_skill;
$avatarUrl = $p?->avatarUrl();
$initials = \App\Models\Profile::initialsFromName($u->name);
@endphp

<div {{ $attributes->class('mb-5 rounded-2xl border border-gray-100 bg-gradient-to-b from-[#F8FAFC] to-white p-4 text-center shadow-sm') }}>
    <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-gray-200 text-lg font-bold text-gray-600 ring-2 ring-white sm:h-[4.5rem] sm:w-[4.5rem] sm:text-xl">
        @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
        @else
        <span class="select-none">{{ $initials }}</span>
        @endif
    </div>
    <p class="line-clamp-2 text-sm font-bold leading-snug text-gray-900">{{ $u->name }}</p>
    @if ($p)
    <p class="mt-1 line-clamp-2 text-center text-xs font-semibold text-[#253B5B]">{{ $p->headlineLabel($membership) }}</p>
    @endif
    <div class="mt-2 flex flex-col items-center gap-2">
        <span class="text-[10px] text-gray-400">{{ $membership->label() }}</span>
        @if (filled($iconic))
        <span class="inline-flex max-w-full items-center justify-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold shadow-sm ring-1 ring-amber-200/80" style="background: linear-gradient(135deg, #FFF7ED, #FFFBEB); color:#92400E">
            <svg class="h-3.5 w-3.5 shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            <span class="truncate">{{ $iconic }}</span>
        </span>
        @endif
    </div>
</div>
