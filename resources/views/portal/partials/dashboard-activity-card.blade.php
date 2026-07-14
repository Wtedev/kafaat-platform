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
$isPath = ($activity['kind'] ?? '') === 'path';
@endphp

<li>
    <article class="flex items-start gap-3 px-4 py-3.5 transition hover:bg-slate-50/80 sm:gap-4 sm:px-5 sm:py-4">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e9eff6] text-[#335483]" aria-hidden="true">
            @if ($isPath)
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
            @else
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
            @endif
        </span>

        <div class="min-w-0 flex-1 text-right">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold leading-snug text-gray-900 sm:text-[0.95rem]">{{ $activity['title'] }}</h3>
                <span class="inline-flex shrink-0 items-center rounded-lg px-2 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">{{ $activity['status_label'] }}</span>
            </div>

            <p class="mt-1 text-xs font-medium text-gray-500">{{ $activity['type_label'] }}</p>

            @if ($activity['progress'] !== null)
            <div class="mt-2.5">
                <div class="mb-1 flex items-center justify-between text-[11px]">
                    <span class="font-semibold tabular-nums text-[#335483]">{{ en_num((float) $activity['progress'], 0) }}%</span>
                    <span class="font-medium text-gray-500">التقدم</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-[#335483] transition-all duration-500" style="width: {{ min(100, max(0, (float) $activity['progress'])) }}%"></div>
                </div>
            </div>
            @endif

            <div class="mt-3">
                <a href="{{ $activity['cta_url'] }}" class="inline-flex items-center gap-1.5 text-xs font-semibold transition hover:opacity-80" style="color:#335483">
                    {{ $activity['cta_label'] }}
                    <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
    </article>
</li>
