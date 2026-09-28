@props([
    'program',
    'index' => 0,
])

@php
    $ended = $program->publicRegistrationUxState() === 'ended';
    $excerpt = $program->descriptionExcerpt();
@endphp

<a
    href="{{ route('public.programs.show', $program->slug) }}"
    @class([
        'group overflow-hidden rounded-2xl border text-right shadow-sm',
        'border-gray-100 bg-white transition duration-300 hover:-translate-y-1 hover:shadow-lg' => ! $ended,
        'program-card--ended border-gray-200 bg-[#F3F4F6] shadow-none' => $ended,
    ])
>
    <x-public.program-catalog-media :program="$program" :index="$index" />

    <div @class(['p-5', 'opacity-55' => $ended])>
        <h3 @class([
            'mb-2 font-bold leading-snug',
            'text-brand transition-colors' => ! $ended,
            'text-gray-500' => $ended,
        ])>{{ $program->title }}</h3>
        @if (filled($excerpt))
            <p @class([
                'line-clamp-2 text-sm leading-relaxed',
                'text-[#6B7280]' => ! $ended,
                'text-gray-400' => $ended,
            ])>{{ $excerpt }}</p>
        @endif
        <div @class([
            'mt-4 flex items-center justify-end gap-1.5 text-xs font-semibold',
            'text-[#335483]' => ! $ended,
            'text-gray-400' => $ended,
        ])>
            عرض البرنامج
            <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
    </div>
</a>
