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
$isPlaceholder = ! $imageUrl || str_contains((string) $imageUrl, 'placeholder');
$isPath = ($activity['kind'] ?? '') === 'path';
@endphp

<article class="group flex min-w-[16.5rem] max-w-[16.5rem] flex-none snap-start flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-[#c5d4e4] hover:shadow-md sm:min-w-[18rem] sm:max-w-[18rem]">
    @if (! $isPlaceholder)
    <div class="relative h-28 w-full shrink-0 overflow-hidden bg-[#e9eff6]">
        <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" loading="lazy" decoding="async" />
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/10 to-transparent" aria-hidden="true"></div>
    </div>
    @else
    <div class="relative flex h-28 w-full shrink-0 items-center justify-center overflow-hidden bg-gradient-to-br from-[#e9eff6] via-[#eef4f9] to-[#dce8f5]">
        <div class="absolute inset-0 opacity-40" style="background-image:radial-gradient(circle at 20% 20%, rgba(255,255,255,0.9), transparent 45%), radial-gradient(circle at 80% 70%, rgba(26,147,153,0.12), transparent 40%);" aria-hidden="true"></div>
        <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-white/90 text-[#335483] shadow-sm ring-1 ring-[#c5d4e4]/60" aria-hidden="true">
            @if ($isPath)
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>
            @else
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
            @endif
        </span>
    </div>
    @endif

    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-lg bg-[#e9eff6] px-2.5 py-1 text-xs font-semibold text-[#335483]">
                <svg class="h-3.5 w-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                {{ $activity['type_label'] }}
            </span>
            <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $activity['status_label'] }}</span>
        </div>

        <h3 class="mb-3 text-right text-sm font-bold leading-snug text-gray-900 sm:text-base">{{ $activity['title'] }}</h3>

        @if ($activity['progress'] !== null)
        <div class="mb-3.5">
            <div class="mb-1.5 flex items-center justify-between text-xs">
                <span class="font-semibold tabular-nums text-[#335483]">{{ en_num((float) $activity['progress'], 0) }}%</span>
                <span class="font-medium text-gray-500">التقدم</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-[#335483] transition-all duration-500" style="width: {{ min(100, max(0, (float) $activity['progress'])) }}%"></div>
            </div>
        </div>
        @endif

        <div class="mt-auto pt-0.5">
            <a href="{{ $activity['cta_url'] }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                {{ $activity['cta_label'] }}
                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</article>
