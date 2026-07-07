@props([
    'href' => null,
    'type' => 'button',
])

@php
    $classes = 'inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-2xl px-8 py-3.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#335483]';
    $style = 'background:linear-gradient(135deg,#335483 0%,#264368 100%)';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes, 'style' => $style]) }}>
        {{ $slot }}
        <svg class="h-4 w-4 shrink-0 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes, 'style' => $style]) }}>
        {{ $slot }}
        <svg class="h-4 w-4 shrink-0 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
    </button>
@endif
