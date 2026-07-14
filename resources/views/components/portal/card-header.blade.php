@props([
    'title' => null,
    'subtitle' => null,
    'variant' => 'soft',
    'heading' => 'h2',
])

@php
    $headingTag = in_array($heading, ['h2', 'h3', 'h4', 'p'], true) ? $heading : 'h2';
    $softHeadingClass = $headingTag === 'h3'
        ? 'text-sm font-bold text-[#335483]'
        : 'text-base font-bold text-[#335483] sm:text-lg';
    $solidHeadingClass = $headingTag === 'h3'
        ? 'text-sm font-bold text-white'
        : 'text-base font-bold text-white sm:text-lg';
@endphp

@if ($variant === 'bar')
    <div {{ $attributes->class('h-1 w-full bg-[#335483]') }} aria-hidden="true"></div>
@elseif ($variant === 'solid')
    <div {{ $attributes->class('flex flex-wrap items-start justify-between gap-3 bg-[#335483] px-4 py-3.5 sm:px-5') }}>
        <div class="min-w-0 flex-1 text-right">
            @if (filled($title))
                <{{ $headingTag }} class="{{ $solidHeadingClass }}">{{ $title }}</{{ $headingTag }}>
            @endif
            @if (filled($subtitle))
                <p class="mt-0.5 text-xs text-white/80 sm:text-sm">{{ $subtitle }}</p>
            @endif
            {{ $slot }}
        </div>
        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-1.5">{{ $actions }}</div>
        @endisset
    </div>
@else
    <div {{ $attributes->class('flex flex-wrap items-start justify-between gap-3 border-b border-[#c5d4e4]/70 bg-[#e9eff6] px-4 py-3.5 sm:px-5') }}>
        <div class="min-w-0 flex-1 text-right">
            @if (filled($title))
                <{{ $headingTag }} class="{{ $softHeadingClass }}">{{ $title }}</{{ $headingTag }}>
            @endif
            @if (filled($subtitle))
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">{{ $subtitle }}</p>
            @endif
            {{ $slot }}
        </div>
        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-1.5">{{ $actions }}</div>
        @endisset
    </div>
@endif
