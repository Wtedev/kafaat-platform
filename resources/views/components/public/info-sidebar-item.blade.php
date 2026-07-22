@props([
    'label',
    'value',
    'dense' => false,
    'href' => null,
    'separated' => false,
])

{{--
  When separated=true, override parent divide-y with one thicker border-t so we never get a broken double line.
--}}
<div @class([
    'flex items-start text-right',
    $dense ? 'gap-2.5 py-2.5' : 'gap-3 py-3',
    $separated ? 'mt-2 !border-t-2 border-gray-200 pt-4' : null,
])>
    <div @class([
        'flex shrink-0 items-center justify-center rounded-xl',
        $dense ? 'h-8 w-8' : 'h-10 w-10',
    ]) style="background:#e9eff6; color:#335483">
        {{ $icon }}
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-xs font-normal leading-snug" style="color:#9CA3AF">{{ $label }}</p>
        @if (filled($href))
            <a
                href="{{ $href }}"
                target="_blank"
                rel="noopener noreferrer"
                @class([
                    $dense ? 'mt-0.5' : 'mt-1',
                    'group inline-flex max-w-full items-start gap-1.5 text-sm font-medium leading-snug text-[#335483] transition-opacity hover:opacity-80',
                ])
                title="فتح الموقع على الخريطة"
            >
                <span class="min-w-0 underline-offset-2 group-hover:underline">{{ $value }}</span>
                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                <span class="sr-only"> (يفتح الخريطة في تبويب جديد)</span>
            </a>
        @else
            <p @class([
                $dense ? 'mt-0.5' : 'mt-1',
                'text-sm font-medium leading-snug text-gray-900',
            ])>
                {{ $value }}
            </p>
        @endif
    </div>
</div>
