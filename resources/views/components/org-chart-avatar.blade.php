{{-- Matches board member photo treatment in public.governance.partials.member-grid --}}
@props([
    'name' => null,
    'photo' => null,
    'team' => false,
    'accent' => null,
])

@php
    $isSanad = $accent === 'sanad';
    $iconColor = $isSanad ? (string) (config('brand.sanad') ?: '#4f53a3') : '#335483';
    $placeholderBg = $isSanad ? (string) (config('brand.sanad_light') ?: '#ededf7') : '#e9eff6';
@endphp

@if (filled($photo))
<img
    src="{{ $photo }}"
    alt="{{ $name ?? '' }}"
    class="oc-avatar oc-avatar--photo{{ $isSanad ? ' oc-avatar--sanad' : '' }}"
    loading="lazy"
    decoding="async"
/>
@elseif ($team)
<div class="oc-avatar oc-avatar--placeholder" style="background: {{ $placeholderBg }}" aria-hidden="true">
    <svg class="oc-avatar__glyph" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
</div>
@else
<div
    class="oc-avatar oc-avatar--placeholder{{ $isSanad ? ' oc-avatar--sanad' : '' }}"
    style="background: {{ $placeholderBg }}"
    aria-hidden="true"
>
    <svg class="oc-avatar__glyph" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
    </svg>
</div>
@endif
