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

<li>
    <article class="flex items-start gap-3 px-4 py-3.5 transition hover:bg-slate-50/80 sm:gap-4 sm:px-5 sm:py-4">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e6f5f6] text-brand-secondary" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
        </span>

        <div class="min-w-0 flex-1 text-right">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold leading-snug text-gray-900 sm:text-[0.95rem]">{{ $row['title'] }}</h3>
                <span class="inline-flex shrink-0 items-center rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">{{ $row['state_label'] }}</span>
            </div>

            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                <span class="font-medium">تطوع</span>
                @if ($row['hours'] !== null)
                <span class="inline-flex items-center gap-1 font-medium">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ en_num((float) $row['hours'], 0) }} ساعة
                </span>
                @endif
            </div>

            <div class="mt-3">
                <a href="{{ $row['cta_url'] }}" class="inline-flex items-center gap-1.5 text-xs font-semibold transition hover:opacity-80" style="color:#335483">
                    {{ $row['cta_label'] }}
                    <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </article>
</li>
