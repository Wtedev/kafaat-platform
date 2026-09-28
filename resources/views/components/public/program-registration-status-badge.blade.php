@props([
    'program',
    'onMedia' => false,
])

@php
    $state = $program->publicRegistrationUxState();
    $label = $program->publicRegistrationUxLabel();

    $tone = match ($state) {
        'open' => $onMedia
            ? 'bg-[#1a9399]/95 text-white shadow-sm ring-1 ring-white/20 backdrop-blur-md'
            : 'bg-[#1a9399]/10 text-[#14686c] ring-1 ring-[#1a9399]/15',
        'upcoming' => $onMedia
            ? 'bg-white/90 text-[#8a5a00] shadow-sm ring-1 ring-[#FCB420]/70 backdrop-blur-md'
            : 'bg-[#FFF6E0] text-[#8a5a00] ring-1 ring-[#FCB420]/50',
        'path' => $onMedia
            ? 'bg-white/90 text-[#335483] shadow-sm ring-1 ring-white/50 backdrop-blur-md'
            : 'bg-[#e9eff6] text-[#335483] ring-1 ring-[#c5d4e4]/70',
        default => $onMedia
            ? 'bg-white/90 text-gray-600 shadow-sm ring-1 ring-black/5 backdrop-blur-md'
            : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200',
    };

    $dot = match ($state) {
        'open' => $onMedia ? 'bg-white' : 'bg-[#1a9399]',
        'upcoming' => 'bg-[#FCB420]',
        'path' => 'bg-[#335483]',
        default => 'bg-gray-400',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold tracking-wide',
    $tone,
]) }}>
    <span class="size-1.5 shrink-0 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $label }}
</span>
