{{-- Org-chart avatar: real photo when present; otherwise solid accent circle + white icon. --}}
@props([
    'name' => '',
    'photo' => null,
    'team' => false,
    'accent' => null,
])

@php
    // Board governance grid keeps its own gray placeholders — this component is org-chart only.
    $accentKey = is_string($accent) ? strtolower(trim($accent)) : '';
    $palette = [
        'sanad' => (string) config('brand.sanad', '#4f53a3'),
        'secondary' => (string) config('brand.secondary', '#1a9399'),
        'teal' => (string) config('brand.secondary', '#1a9399'),
        'primary' => (string) config('brand.primary', '#335483'),
        'danger' => (string) config('brand.danger', '#ec6056'),
    ];
    $placeholderBg = $palette[$accentKey] ?? $palette['primary'];
    $iconColor = '#ffffff';
    $frame = 'mx-auto mb-4 h-20 w-20 rounded-full border-2 border-gray-100 shadow-sm';
@endphp

@if (filled($photo))
    <img
        src="{{ $photo }}"
        alt="{{ $name }}"
        {{ $attributes->merge(['class' => $frame.' object-cover']) }}
        loading="lazy"
    />
@else
    <div
        {{ $attributes->merge([
            'class' => 'flex items-center justify-center '.$frame,
            'style' => 'background:'.$placeholderBg,
            'aria-hidden' => 'true',
        ]) }}
    >
        @if ($team)
            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        @else
            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="{{ $iconColor }}" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        @endif
    </div>
@endif
