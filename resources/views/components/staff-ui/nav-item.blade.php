@props([
    'icon',
    'href' => '#',
    'active' => false,
    'count' => null,
    'flipIcon' => false,
    'disabled' => false,
])

@php
    $tag = $disabled ? 'span' : 'a';
@endphp

<{{ $tag }}
    @unless ($disabled) href="{{ $href }}" @endunless
    @class(['sui-nav__item', 'is-active' => $active && ! $disabled, 'is-disabled' => $disabled])
    @if ($active && ! $disabled) aria-current="page" @endif
    @if ($disabled) aria-disabled="true" @endif
>
    <i data-lucide="{{ $icon }}" @class(['sui-icon', 'sui-icon--flip' => $flipIcon])></i>
    <span class="sui-nav__text">{{ $slot }}</span>
    @if ($count !== null)
        <x-staff-ui.count class="sui-nav__count">{{ $count }}</x-staff-ui.count>
    @endif
</{{ $tag }}>
