@props([
    'programCounts' => collect(),
])

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$intro = config('competency_tracks.intro', []);
$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
@endphp

<section {{ $attributes->merge(['class' => '']) }}>
    <div class="mb-10 text-center">
        <p class="mb-3 text-sm font-semibold uppercase tracking-widest" style="color:#1a9399">{{ $intro['badge'] ?? 'مسارات الكفاءة' }}</p>
        <h2 class="text-3xl font-bold sm:text-4xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h2>
        <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed" style="color:#6B7280">{{ $intro['subtitle'] ?? '' }}</p>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        @foreach ($order as $trackKey)
            @php
            $track = CompetencyTrack::from($trackKey);
            $meta = $tracks[$trackKey] ?? [];
            $color = $meta['color'] ?? '#335483';
            $count = (int) ($programCounts[$trackKey] ?? 0);
            @endphp
            <a href="{{ route('public.programs.track', $track) }}"
               class="vm-card group relative flex h-full flex-col overflow-hidden rounded-3xl border border-gray-100 bg-white p-6 text-right shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
                <div class="absolute inset-x-0 top-0 h-1" style="background:{{ $color }}"></div>

                <div class="mb-5 flex items-start justify-between gap-3">
                    <span class="inline-flex rounded-lg px-2.5 py-1 text-[11px] font-bold tabular-nums" style="background:{{ $color }}12; color:{{ $color }}">
                        {{ en_num($count) }} برنامج
                    </span>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl transition-transform duration-300 group-hover:scale-105" style="background:{{ $color }}14">
                        <span class="h-3 w-3 rounded-full" style="background:{{ $color }}"></span>
                    </div>
                </div>

                <h3 class="mb-2 text-lg font-bold leading-snug transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $track->shortLabel() }}</h3>
                <p class="mb-6 line-clamp-3 flex-1 text-sm leading-relaxed" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>

                <span class="inline-flex items-center justify-end gap-1.5 text-sm font-semibold" style="color:{{ $color }}">
                    استكشف البرامج
                    <svg class="h-4 w-4 rotate-180 transition-transform duration-300 group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-10 flex justify-center">
        <a href="{{ route('public.tracks.index') }}"
           class="inline-flex items-center gap-2 rounded-2xl border-2 px-6 py-3 text-sm font-semibold transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
           style="border-color:#335483; color:#335483">
            تعرّف على فكرة مسارات الكفاءة
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</section>
