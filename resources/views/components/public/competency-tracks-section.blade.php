@props([
    'variant' => 'filter',
    'activeTrack' => null,
    'programCounts' => collect(),
])

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$intro = config('competency_tracks.intro', []);
$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
$totalCount = (int) $programCounts->sum();
$activeMeta = $activeTrack ? ($tracks[$activeTrack->value] ?? []) : null;
$activeColor = $activeMeta['color'] ?? '#335483';
@endphp

@if ($variant === 'showcase')
<section {{ $attributes->merge(['class' => '']) }}>
    <div class="mb-10 text-center sm:text-right">
        <p class="mb-2 text-sm font-semibold tracking-wide" style="color:#1a9399">{{ $intro['badge'] ?? 'مسارات الكفاءة' }}</p>
        <h2 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed sm:mx-0 sm:text-base" style="color:#6B7280">{{ $intro['subtitle'] ?? '' }}</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
        @foreach ($order as $trackKey)
            @php
            $track = CompetencyTrack::from($trackKey);
            $meta = $tracks[$trackKey] ?? [];
            $color = $meta['color'] ?? '#335483';
            $count = (int) ($programCounts[$trackKey] ?? 0);
            @endphp
            <a href="{{ route('public.programs.index', ['track' => $trackKey]) }}"
               class="group flex h-full flex-col rounded-2xl border border-gray-100 bg-white p-5 text-right shadow-sm transition hover:-translate-y-0.5 hover:border-gray-200 hover:shadow-md sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold tabular-nums" style="background:{{ $color }}14; color:{{ $color }}">
                        {{ en_num($count) }} برنامج
                    </span>
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl" style="background:{{ $color }}14">
                        <span class="h-3 w-3 rounded-full" style="background:{{ $color }}"></span>
                    </span>
                </div>
                <h3 class="mb-2 text-lg font-bold leading-snug transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $track->shortLabel() }}</h3>
                <p class="mb-4 flex-1 text-sm leading-relaxed" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>
                <span class="inline-flex items-center justify-end gap-1.5 text-sm font-semibold" style="color:{{ $color }}">
                    استكشف البرامج
                    <svg class="h-4 w-4 rotate-180 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-8 flex flex-col items-center justify-between gap-4 border-t border-gray-100 pt-6 sm:flex-row sm:items-center">
        <p class="text-sm" style="color:#6B7280">
            <span class="font-bold tabular-nums" style="color:#111827">{{ en_num($totalCount) }}</span>
            برنامج منشور عبر المسارات الثلاثة
        </p>
        <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:shadow-md" style="background:#335483">
            عرض جميع البرامج
            <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
</section>
@else
<div {{ $attributes->merge(['class' => '']) }}>
    <div class="overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <nav class="inline-flex min-w-full items-center gap-1 rounded-xl bg-[#EEF2F6] p-1 sm:min-w-0"
             aria-label="تصفية البرامج حسب مسار الكفاءة"
             role="tablist">
            <a href="{{ route('public.programs.index') }}"
               role="tab"
               @if ($activeTrack === null) aria-selected="true" @endif
               class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold whitespace-nowrap transition {{ $activeTrack === null ? 'bg-white text-[#335483] shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                الكل
                <span class="text-[11px] font-bold tabular-nums {{ $activeTrack === null ? 'text-[#335483]/70' : 'text-gray-400' }}">{{ en_num($totalCount) }}</span>
            </a>

            @foreach ($order as $trackKey)
                @php
                $track = CompetencyTrack::from($trackKey);
                $meta = $tracks[$trackKey] ?? [];
                $color = $meta['color'] ?? '#335483';
                $isActive = $activeTrack?->value === $trackKey;
                $count = (int) ($programCounts[$trackKey] ?? 0);
                @endphp
                <a href="{{ route('public.programs.index', ['track' => $trackKey]) }}"
                   role="tab"
                   @if ($isActive) aria-selected="true" @endif
                   class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold whitespace-nowrap transition {{ $isActive ? 'bg-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                   @if ($isActive) style="color:{{ $color }}" @endif>
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background:{{ $color }}"></span>
                    <span class="hidden sm:inline">{{ $track->shortLabel() }}</span>
                    <span class="sm:hidden">{{ str_replace('الكفاءة ', '', $track->shortLabel()) }}</span>
                    <span class="text-[11px] font-bold tabular-nums {{ $isActive ? '' : 'text-gray-400' }}" @if ($isActive) style="color:{{ $color }}; opacity:0.75" @endif>{{ en_num($count) }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    @if ($activeTrack && $activeMeta)
    <div class="mt-5 border-s-4 ps-4" style="border-color:{{ $activeColor }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 text-right">
                <p class="text-sm font-bold" style="color:#111827">{{ $activeTrack->label() }}</p>
                <p class="mt-1 text-sm leading-relaxed" style="color:#6B7280">{{ $activeMeta['description'] ?? '' }}</p>
                @if (! empty($activeMeta['focus']))
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    @foreach ($activeMeta['focus'] as $item)
                    <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-medium" style="background:{{ $activeColor }}10; color:{{ $activeColor }}">{{ $item }}</span>
                    @endforeach
                </div>
                @endif
            </div>
            <a href="{{ route('public.programs.index') }}" class="shrink-0 text-xs font-semibold text-gray-400 transition hover:text-[#335483]">
                إلغاء التصفية
            </a>
        </div>
    </div>
    @endif
</div>
@endif
