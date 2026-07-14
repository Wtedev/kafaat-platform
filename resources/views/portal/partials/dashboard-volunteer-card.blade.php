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
$imageUrl = $row['image_url'] ?? null;
$isPlaceholder = ! $imageUrl || str_contains((string) $imageUrl, 'placeholder');
@endphp

<article class="group flex min-w-[16.5rem] max-w-[16.5rem] flex-none snap-start flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-[#c5d4e4] hover:shadow-md sm:min-w-[18rem] sm:max-w-[18rem]">
    @if (! $isPlaceholder)
    <div class="relative h-28 w-full shrink-0 overflow-hidden bg-gray-100">
        <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]" loading="lazy" decoding="async" />
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/10 to-transparent" aria-hidden="true"></div>
    </div>
    @else
    <div class="relative flex h-28 w-full shrink-0 items-center justify-center overflow-hidden bg-gradient-to-br from-[#e6f5f6] via-[#eef8f8] to-[#d4ecee]">
        <div class="absolute inset-0 opacity-40" style="background-image:radial-gradient(circle at 25% 25%, rgba(255,255,255,0.95), transparent 45%), radial-gradient(circle at 75% 75%, rgba(51,84,131,0.1), transparent 40%);" aria-hidden="true"></div>
        <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-white/90 text-brand-secondary shadow-sm ring-1 ring-[#b8e0e2]/70" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
        </span>
    </div>
    @endif

    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-1.5">
            <span class="inline-flex items-center gap-1 rounded-lg bg-[#e9eff6] px-2.5 py-1 text-xs font-semibold text-[#335483]">
                <svg class="h-3.5 w-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                تطوع
            </span>
            <span class="rounded-lg px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $row['state_label'] }}</span>
        </div>

        <h3 class="text-right text-sm font-bold leading-snug text-gray-900 sm:text-base">{{ $row['title'] }}</h3>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
            @if ($row['hours'] !== null)
            <span class="inline-flex items-center gap-1 rounded-lg bg-gray-50 px-2 py-1 font-medium text-gray-600">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ en_num((float) $row['hours'], 0) }} ساعة
            </span>
            @else
            <span class="text-gray-400">—</span>
            @endif
        </div>

        <div class="mt-auto pt-3.5">
            <a href="{{ $row['cta_url'] }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                {{ $row['cta_label'] }}
                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</article>
