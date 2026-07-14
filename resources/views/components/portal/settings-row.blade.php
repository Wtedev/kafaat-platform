@props([
    'href' => null,
    'label',
    'hint' => null,
    'danger' => false,
    'button' => false,
])

@php
$base = 'flex w-full items-center gap-3 px-4 py-3.5 text-right transition sm:px-5';
$hover = $danger ? 'hover:bg-red-50/80' : 'hover:bg-slate-50/90';
$tag = $button ? 'button' : 'a';
@endphp

<{{ $tag }}
    @if ($button) type="button" @else href="{{ $href }}" @endif
    {{ $attributes->class([$base, $hover]) }}
>
    @isset($icon)
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $danger ? 'bg-red-100/80 text-red-700' : 'bg-[#e9eff6] text-[#335483]' }}">
        {{ $icon }}
    </span>
    @endisset
    <span class="min-w-0 flex-1">
        <span class="block text-sm font-semibold {{ $danger ? 'text-red-900' : 'text-gray-900' }}">{{ $label }}</span>
        @if ($hint)
        <span class="mt-0.5 block text-xs {{ $danger ? 'text-red-700/90' : 'text-gray-500' }}">{{ $hint }}</span>
        @endif
    </span>
    <svg class="h-4 w-4 shrink-0 rotate-180 {{ $danger ? 'text-red-300' : 'text-slate-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
</{{ $tag }}>
