@php
$brandTones = config('brand.classes');
$toneClasses = [
    'primary' => $brandTones['tone_primary'],
    'secondary' => $brandTones['tone_secondary'],
    'accent' => $brandTones['tone_accent'],
    'muted' => $brandTones['tone_muted'],
    'slate' => $brandTones['tone_muted'],
];
$badgeClass = $toneClasses[$row['state_tone']] ?? $toneClasses['muted'];
@endphp

<article class="group flex min-w-[16.5rem] max-w-[16.5rem] flex-none snap-start flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-[#c5d4e4] hover:shadow-md sm:min-w-[18rem] sm:max-w-[18rem]">
    <div class="h-24 w-full shrink-0 overflow-hidden bg-gray-100">
        <img src="{{ $row['image_url'] ?? '' }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" loading="lazy" decoding="async" />
    </div>
    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2 flex justify-end">
            <span class="inline-flex items-center rounded-lg bg-[#e9eff6] px-2.5 py-1 text-xs font-semibold text-[#335483]">تطوع</span>
        </div>
        <h3 class="text-right text-sm font-bold leading-snug text-gray-900">{{ $row['title'] }}</h3>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
            @if ($row['hours'] !== null)
            <span class="rounded-lg bg-gray-50 px-2 py-1 font-medium text-gray-600">{{ en_num((float) $row['hours'], 0) }} ساعة</span>
            @else
            <span class="text-gray-400">—</span>
            @endif
            <span class="rounded-lg px-2 py-1 font-semibold {{ $badgeClass }}">{{ $row['state_label'] }}</span>
        </div>
        <div class="mt-4 flex justify-end">
            <a href="{{ $row['cta_url'] }}" class="inline-flex items-center gap-1.5 text-sm font-semibold transition hover:underline" style="color:#335483">
                {{ $row['cta_label'] }}
                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</article>
