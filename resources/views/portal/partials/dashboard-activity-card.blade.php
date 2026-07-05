@php
$brandTones = config('brand.classes');
$toneClasses = [
    'primary' => $brandTones['tone_primary'],
    'secondary' => $brandTones['tone_secondary'],
    'accent' => $brandTones['tone_accent'],
    'muted' => $brandTones['tone_muted'],
    // توافق مع القيم القديمة
    'blue' => $brandTones['tone_primary'],
    'emerald' => $brandTones['tone_secondary'],
    'indigo' => $brandTones['tone_primary'],
    'amber' => $brandTones['tone_accent'],
    'rose' => $brandTones['tone_accent'],
    'slate' => $brandTones['tone_muted'],
];
$badgeClass = $toneClasses[$activity['status_tone']] ?? $toneClasses['muted'];
$isDiscover = ! empty($activity['discover']);
@endphp

<article class="group flex min-w-[17.5rem] max-w-[17.5rem] flex-none snap-start flex-col rounded-2xl border border-gray-100/80 bg-white p-5 shadow-sm transition hover:border-brand-border hover:shadow-md sm:min-w-[19rem] sm:max-w-[19rem]">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold bg-brand-light text-brand">{{ $activity['type_label'] }}</span>
        <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $activity['status_label'] }}</span>
    </div>
    <h3 class="mb-3 text-right text-base font-bold leading-snug text-gray-900">{{ $activity['title'] }}</h3>

    @if ($activity['progress'] !== null)
    <div class="mb-4">
        <div class="mb-1 flex justify-between text-xs text-gray-500">
            <span>{{ en_num((float) $activity['progress'], 0) }}%</span>
            <span>التقدم</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full transition-all duration-500" style="width: {{ min(100, max(0, (float) $activity['progress'])) }}%; background: linear-gradient(to left, #1a9399, #335483)"></div>
        </div>
    </div>
    @endif

    <div class="mt-auto flex justify-end pt-2">
        @if ($isDiscover)
        <a href="{{ $activity['cta_url'] }}" class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-brand ring-1 ring-brand-border transition hover:bg-brand-light">
            {{ $activity['cta_label'] }}
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @else
        <a href="{{ $activity['cta_url'] }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 bg-brand">
            {{ $activity['cta_label'] }}
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endif
    </div>
</article>
