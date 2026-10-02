@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconOnly' => false,
    'type' => 'button',
    'href' => null,
    'flipIcon' => false,
])

@php
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->class([
        'sui-btn',
        'sui-btn--'.$variant,
        'sui-btn--'.$size,
        'sui-btn--icon' => $iconOnly,
    ]) }}
>
    @if ($icon)
        <i data-lucide="{{ $icon }}" @class(['sui-icon', 'sui-icon--flip' => $flipIcon])></i>
    @endif
    @unless ($iconOnly)
        <span>{{ $slot }}</span>
    @endunless
</{{ $tag }}>
