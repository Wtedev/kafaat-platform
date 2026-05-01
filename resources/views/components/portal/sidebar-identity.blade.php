@props([])

@php
$u = auth()->user();
$p = $u->profile;
$avatarUrl = $p?->avatarUrl();
$initials = \App\Models\Profile::initialsFromName($u->name);
@endphp

<div {{ $attributes->class('shrink-0 text-center') }}>
    <div class="mx-auto mb-2 flex h-[3.25rem] w-[3.25rem] items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200/70 text-base font-bold text-slate-700 sm:h-14 sm:w-14 sm:text-lg">
        @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
        @else
        <span class="select-none">{{ $initials }}</span>
        @endif
    </div>
    <p class="line-clamp-2 text-sm font-bold leading-snug text-slate-900">{{ $u->name }}</p>
    <x-portal.profile-badges class="mt-3 w-full" :profile="$p" align="center" />
</div>
