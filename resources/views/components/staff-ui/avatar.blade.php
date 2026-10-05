@props([
    'name' => '',
    'size' => 'sm',
])

@php
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice(array_values(array_filter($parts)), 0, 2) as $part) {
        $initials .= mb_substr($part, 0, 1);
    }
@endphp

<span {{ $attributes->class(['sui-avatar', 'sui-avatar--md' => $size === 'md']) }} aria-hidden="true">{{ $initials !== '' ? $initials : 'ك' }}</span>
