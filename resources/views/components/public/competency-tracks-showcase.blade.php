@props([
    'programCounts' => collect(),
    'activeTrack' => null,
    'compact' => false,
])

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$intro = config('competency_tracks.intro', []);
$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
@endphp

<section {{ $attributes->merge(['class' => '']) }}>
    @unless ($compact)
    <div class="mb-10 text-center">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold" style="border-color:#c5d4e4; background:#e9eff6; color:#335483">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            {{ $intro['badge'] ?? 'مسارات الكفاءة' }}
        </div>
        <h2 class="mb-3 text-3xl font-bold sm:text-4xl">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h2>
        <p class="mx-auto max-w-3xl text-base leading-relaxed sm:text-lg" style="color:#6B7280">{{ $intro['subtitle'] ?? '' }}</p>
    </div>
    @endunless

    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
        @foreach ($order as $trackKey)
            @php
            $track = CompetencyTrack::from($trackKey);
            $meta = $tracks[$trackKey] ?? [];
            $isActive = $activeTrack?->value === $trackKey;
            $count = (int) ($programCounts[$trackKey] ?? 0);
            $from = $meta['gradient_from'] ?? '#335483';
            $to = $meta['gradient_to'] ?? '#243a55';
            @endphp
            <a href="{{ route('public.programs.track', $trackKey) }}"
               class="group relative flex min-h-[22rem] flex-col overflow-hidden rounded-[1.75rem] p-6 text-right shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl {{ $isActive ? 'ring-4 ring-white/80 ring-offset-2 ring-offset-[#F7FAFC]' : '' }}"
               style="background: linear-gradient(160deg, {{ $from }} 0%, {{ $to }} 100%)">
                <div class="absolute -left-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-10 -right-6 h-40 w-40 rounded-full bg-black/10"></div>

                <div class="relative z-10 flex flex-1 flex-col">
                    <p class="mb-2 text-xs font-bold uppercase tracking-widest text-white/75">مسار</p>
                    <h3 class="mb-3 text-xl font-bold leading-snug text-white">{{ $track->label() }}</h3>
                    <p class="mb-5 flex-1 text-sm leading-relaxed text-white/90">{{ $meta['description'] ?? '' }}</p>

                    <div class="mb-5 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                        <p class="mb-2 text-[11px] font-semibold text-white/70">مجالات الظهور</p>
                        <ul class="space-y-1.5">
                            @foreach (($meta['focus'] ?? []) as $item)
                            <li class="flex items-center justify-end gap-2 text-xs text-white/95">
                                <span>{{ $item }}</span>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-white/80"></span>
                            </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-white transition-transform group-hover:-translate-x-0.5">
                            استكشف البرامج
                            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                        <span class="rounded-xl bg-white/15 px-3 py-1.5 text-xs font-bold text-white">
                            {{ en_num($count) }} برنامج
                        </span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @unless ($compact)
    <div class="mt-10 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
        <div class="mb-6 text-center">
            <h3 class="text-xl font-bold">أرقام تعكس تنوّع مساراتنا</h3>
            <p class="mt-2 text-sm" style="color:#6B7280">برامج الجمعية موزّعة على مسارات الكفاءة الثلاثة لخدمة احتياجات الشباب المتنوعة.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($order as $trackKey)
                @php
                $track = CompetencyTrack::from($trackKey);
                $meta = $tracks[$trackKey] ?? [];
                $count = (int) ($programCounts[$trackKey] ?? 0);
                @endphp
                <div class="rounded-2xl border border-gray-100 bg-[#F8FAFC] px-5 py-4 text-center">
                    <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-xl" style="background: {{ ($meta['color'] ?? '#335483') }}22">
                        <span class="h-3 w-3 rounded-full" style="background: {{ $meta['color'] ?? '#335483' }}"></span>
                    </div>
                    <div class="text-3xl font-bold tabular-nums" style="color:var(--brand-body)">{{ en_num($count) }}</div>
                    <div class="mt-1 text-sm font-semibold" style="color:#335483">{{ $track->shortLabel() }}</div>
                    <div class="mt-1 text-xs" style="color:#9CA3AF">{{ $meta['stat_label'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('public.tracks.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold transition hover:bg-gray-50" style="color:#335483">
                دليل مسارات العمل
            </a>
            <a href="{{ route('public.programs.track', \App\Enums\CompetencyTrack::Self) }}" class="inline-flex items-center gap-2 rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" style="background:{{ $tracks[CompetencyTrack::Self->value]['color'] ?? config('brand.secondary') }}">
                عرض جميع البرامج
            </a>
        </div>
    </div>
    @endunless
</section>
