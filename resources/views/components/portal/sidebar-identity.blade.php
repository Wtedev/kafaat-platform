@props([])

@php
$u = auth()->user();
$p = $u->profile;
$membership = \App\Services\Portal\BeneficiaryMembershipResolver::resolve($u);
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
    <x-portal.profile-badges class="mt-2 w-full" :profile="$p" align="center" />
</div>
