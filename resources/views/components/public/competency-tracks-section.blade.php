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
    <div class="mb-12 text-center">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold" style="border-color:#c5d4e4; background:#e9eff6; color:#335483">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            {{ $intro['badge'] ?? 'مسارات الكفاءة' }}
        </div>
        <h2 class="text-3xl font-bold sm:text-4xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h2>
        <p class="mx-auto mt-4 max-w-3xl text-base leading-relaxed sm:text-lg" style="color:#6B7280">{{ $intro['subtitle'] ?? '' }}</p>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-3 md:gap-6">
        @foreach ($order as $trackKey)
            @php
            $track = CompetencyTrack::from($trackKey);
            $meta = $tracks[$trackKey] ?? [];
            $count = (int) ($programCounts[$trackKey] ?? 0);
            $from = $meta['gradient_from'] ?? '#335483';
            $to = $meta['gradient_to'] ?? '#243a55';
            @endphp
            <a href="{{ route('public.programs.track', $track) }}"
               class="group relative flex min-h-[20rem] flex-col overflow-hidden rounded-[1.75rem] p-6 text-right shadow-lg transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl sm:min-h-[22rem] sm:p-7"
               style="background: linear-gradient(160deg, {{ $from }} 0%, {{ $to }} 100%)">
                <div class="pointer-events-none absolute -left-10 -top-10 h-36 w-36 rounded-full bg-white/10" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-12 -right-8 h-44 w-44 rounded-full bg-black/10" aria-hidden="true"></div>

                <div class="relative z-10 flex flex-1 flex-col">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <span class="rounded-xl bg-white/15 px-3 py-1.5 text-xs font-bold text-white tabular-nums backdrop-blur-sm">
                            {{ en_num($count) }} برنامج
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-white/70">مسار</span>
                    </div>

                    <h3 class="mb-3 text-xl font-bold leading-snug text-white sm:text-[1.35rem]">{{ $track->label() }}</h3>
                    <p class="mb-5 line-clamp-3 flex-1 text-sm leading-relaxed text-white/90">{{ $meta['description'] ?? '' }}</p>

                    @if (! empty($meta['focus']))
                    <div class="mb-5 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                        <p class="mb-2 text-[11px] font-semibold text-white/70">مجالات الظهور</p>
                        <ul class="space-y-1.5">
                            @foreach (array_slice($meta['focus'], 0, 3) as $item)
                            <li class="flex items-center justify-end gap-2 text-xs text-white/95">
                                <span>{{ $item }}</span>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-white/80"></span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <span class="mt-auto inline-flex items-center justify-end gap-1.5 text-sm font-semibold text-white transition-transform duration-300 group-hover:-translate-x-1">
                        استكشف البرامج
                        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-10 flex justify-center">
        <a href="{{ route('public.tracks.index') }}"
           class="inline-flex items-center gap-2 rounded-2xl border-2 border-[#335483] bg-white px-6 py-3 text-sm font-semibold text-[#335483] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#335483] hover:text-white hover:shadow-md">
            تعرّف على فكرة مسارات الكفاءة
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</section>
