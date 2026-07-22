@props([
    'label',
    'value',
    'dense' => false,
    'href' => null,
    'separated' => false,
])

<div @class([
    'flex items-start text-right',
    $dense ? 'gap-2.5 py-2.5' : 'gap-3 py-3',
    $separated ? 'mt-2 border-t-2 border-gray-200 pt-4' : null,
])>
    <div @class([
        'flex shrink-0 items-center justify-center rounded-xl',
        $dense ? 'h-8 w-8' : 'h-10 w-10',
    ]) style="background:#e9eff6">
        @if (filled($href))
            <a
                href="{{ $href }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex text-[#335483] transition-opacity hover:opacity-80"
                aria-label="فتح الموقع على الخريطة"
                title="فتح الموقع على الخريطة"
            >
                {{ $icon }}
            </a>
        @else
            {{ $icon }}
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-xs font-normal leading-snug" style="color:#9CA3AF">{{ $label }}</p>
        <p @class([
            $dense ? 'mt-0.5' : 'mt-1',
            'flex items-start gap-1.5 text-sm font-medium leading-snug text-gray-900',
        ])>
            <span class="min-w-0">{{ $value }}</span>
            @if (filled($href))
                <a
                    href="{{ $href }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-0.5 inline-flex shrink-0 text-[#335483] transition-opacity hover:opacity-80"
                    aria-label="الاتجاهات على الخريطة"
                    title="الاتجاهات على الخريطة"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </a>
            @endif
        </p>
    </div>
</div>
