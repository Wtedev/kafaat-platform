@php
$brandTones = config('brand.classes');
$toneClasses = [
    'primary' => $brandTones['tone_primary'],
    'secondary' => $brandTones['tone_secondary'],
    'accent' => $brandTones['tone_accent'],
    'muted' => $brandTones['tone_muted'],
    'blue' => $brandTones['tone_primary'],
    'emerald' => $brandTones['tone_secondary'],
    'indigo' => $brandTones['tone_primary'],
    'amber' => $brandTones['tone_accent'],
    'rose' => $brandTones['tone_accent'],
    'slate' => $brandTones['tone_muted'],
];
$badgeClass = $toneClasses[$activity['status_tone']] ?? $toneClasses['muted'];
$imageUrl = $activity['image_url'] ?? null;
@endphp

<article class="group flex min-w-[16.5rem] max-w-[16.5rem] flex-none snap-start flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-[#c5d4e4] hover:shadow-md sm:min-w-[18rem] sm:max-w-[18rem]">
    @if ($imageUrl)
    <div class="h-24 w-full shrink-0 overflow-hidden bg-[#e9eff6]">
        <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" loading="lazy" decoding="async" />
    </div>
    @endif
    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
            <span class="inline-flex items-center rounded-lg bg-[#e9eff6] px-2.5 py-1 text-xs font-semibold text-[#335483]">{{ $activity['type_label'] }}</span>
            <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $activity['status_label'] }}</span>
        </div>
        <h3 class="mb-3 text-right text-sm font-bold leading-snug text-gray-900 sm:text-base">{{ $activity['title'] }}</h3>

        @if ($activity['progress'] !== null)
        <div class="mb-3">
            <div class="mb-1 flex justify-between text-xs text-gray-500">
                <span>{{ en_num((float) $activity['progress'], 0) }}%</span>
                <span>التقدم</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-[#335483] transition-all duration-500" style="width: {{ min(100, max(0, (float) $activity['progress'])) }}%"></div>
            </div>
        </div>
        @endif

        <div class="mt-auto flex justify-end pt-1">
            <a href="{{ $activity['cta_url'] }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                {{ $activity['cta_label'] }}
                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</article>
