{{-- Exact match for board member photo treatment (member-grid). --}}
@props([
    'name' => '',
    'photo' => null,
    'team' => false,
])

@php
    // Board: mb-4 h-20 w-20 rounded-full border-2 border-gray-100 object-cover shadow-sm
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
            'style' => 'background:#e9eff6',
            'aria-hidden' => 'true',
        ]) }}
    >
        @if ($team)
            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="#335483" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        @else
            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="#335483" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        @endif
    </div>
@endif
