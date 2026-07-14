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

<article class="portal-dash-card group flex min-w-[17.5rem] max-w-[17.5rem] flex-none snap-start flex-col sm:min-w-[19rem] sm:max-w-[19rem]">
    <div class="relative h-24 w-full shrink-0 overflow-hidden bg-slate-100">
        <img src="{{ $row['image_url'] ?? '' }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" decoding="async" />
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
    </div>
    <div class="flex flex-1 flex-col p-4">
        <h3 class="text-right text-sm font-bold leading-snug text-slate-900">{{ $row['title'] }}</h3>
        @if (! empty($row['meta']))
        <p class="mt-1.5 text-right text-xs text-slate-500">{{ $row['meta'] }}</p>
        @endif
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
            @if ($row['hours'] !== null)
            <span class="rounded-lg bg-slate-50 px-2 py-1 font-medium text-slate-600">{{ en_num((float) $row['hours'], 0) }} ساعة</span>
            @else
            <span class="text-slate-400">—</span>
            @endif
            <span class="rounded-lg px-2 py-1 font-semibold {{ $badgeClass }}">{{ $row['state_label'] }}</span>
        </div>
        <div class="mt-4 flex justify-end">
            <a href="{{ $row['cta_url'] }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand transition hover:underline">
                {{ $row['cta_label'] }}
                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</article>
