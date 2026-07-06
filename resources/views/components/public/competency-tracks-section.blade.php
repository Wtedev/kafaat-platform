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
    <div class="mb-8 text-center sm:text-right">
        <p class="mb-2 text-sm font-semibold tracking-wide" style="color:#1a9399">{{ $intro['badge'] ?? 'مسارات الكفاءة' }}</p>
        <h2 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h2>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed sm:mx-0" style="color:#6B7280">{{ \Illuminate\Support\Str::limit($intro['subtitle'] ?? '', 120) }}</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ($order as $trackKey)
            @php
            $track = CompetencyTrack::from($trackKey);
            $meta = $tracks[$trackKey] ?? [];
            $color = $meta['color'] ?? '#335483';
            $count = (int) ($programCounts[$trackKey] ?? 0);
            @endphp
            <a href="{{ route('public.programs.track', $track) }}"
               class="group flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 text-right shadow-sm transition hover:border-gray-200 hover:shadow-md">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background:{{ $color }}14">
                    <span class="h-2 w-2 rounded-full" style="background:{{ $color }}"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold" style="color:#111827">{{ $track->shortLabel() }}</p>
                    <p class="text-xs tabular-nums" style="color:#9CA3AF">{{ en_num($count) }} برنامج</p>
                </div>
                <svg class="h-4 w-4 shrink-0 rotate-180 text-gray-300 transition group-hover:text-[#335483]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        @endforeach
    </div>

    <div class="mt-6 text-center sm:text-right">
        <a href="{{ route('public.tracks.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#335483">
            ما هي مسارات الكفاءة؟
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</section>
