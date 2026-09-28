@props([
    'program',
    'onMedia' => false,
])

@php
    $state = $program->publicRegistrationUxState();
    $label = $program->publicRegistrationUxLabel();

    $tone = match ($state) {
        'open' => $onMedia
            ? 'bg-[#1a9399] text-white shadow-sm ring-1 ring-white/25'
            : 'bg-[#1a9399]/10 text-[#14686c] ring-1 ring-[#1a9399]/20',
        'upcoming' => $onMedia
            ? 'bg-[#fff7e6] text-[#8a5a00] shadow-sm ring-1 ring-[#FCB420]/80'
            : 'bg-[#FCB420]/20 text-[#8a5a00] ring-1 ring-[#FCB420]/40',
        'path' => $onMedia
            ? 'bg-white/95 text-[#335483] shadow-sm ring-1 ring-white/60'
            : 'bg-[#e9eff6] text-[#335483] ring-1 ring-[#c5d4e4]/70',
        default => $onMedia
            ? 'bg-gray-900/80 text-white shadow-sm ring-1 ring-white/10'
            : 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold leading-none', $tone]) }}>
    {{ $label }}
</span>
