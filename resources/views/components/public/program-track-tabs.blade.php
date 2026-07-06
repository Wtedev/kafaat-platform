@props([
    'activeTrack' => null,
    'programCounts' => collect(),
])

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
$totalCount = (int) $programCounts->sum();
$activeMeta = $activeTrack ? ($tracks[$activeTrack->value] ?? []) : null;
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <div class="overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="inline-flex min-w-full items-stretch gap-1 rounded-2xl border border-gray-100 bg-[#F8FAFC] p-1.5 shadow-sm sm:gap-1.5"
             role="tablist"
             aria-label="تصفية البرامج حسب مسار الكفاءة">
            <a href="{{ route('public.programs.index') }}"
               role="tab"
               aria-selected="{{ $activeTrack === null ? 'true' : 'false' }}"
               class="group relative flex min-w-[7.5rem] flex-1 flex-col items-center justify-center gap-1 rounded-xl px-3 py-3 text-center transition-all duration-300 sm:min-w-0 sm:px-4 {{ $activeTrack === null ? 'bg-white text-[#335483] shadow-md ring-1 ring-gray-100' : 'text-gray-500 hover:bg-white/70 hover:text-gray-700' }}">
                <span class="text-sm font-bold">جميع البرامج</span>
                <span class="inline-flex min-w-[1.75rem] items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums {{ $activeTrack === null ? 'bg-[#335483] text-white' : 'bg-gray-200/80 text-gray-600' }}">
                    {{ en_num($totalCount) }}
                </span>
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
                   aria-selected="{{ $isActive ? 'true' : 'false' }}"
                   class="group relative flex min-w-[7.5rem] flex-1 flex-col items-center justify-center gap-1 rounded-xl px-3 py-3 text-center transition-all duration-300 sm:min-w-0 sm:px-4 {{ $isActive ? 'text-white shadow-lg' : 'text-gray-500 hover:bg-white/70 hover:text-gray-700' }}"
                   @if ($isActive) style="background: linear-gradient(145deg, {{ $meta['gradient_from'] ?? $color }} 0%, {{ $meta['gradient_to'] ?? $color }} 100%)" @endif>
                    @unless ($isActive)
                    <span class="absolute inset-x-3 top-0 h-0.5 rounded-full opacity-0 transition-opacity group-hover:opacity-100" style="background:{{ $color }}"></span>
                    @endunless
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $isActive ? 'bg-white/90' : '' }}" @unless($isActive) style="background:{{ $color }}" @endunless></span>
                        <span class="text-sm font-bold leading-tight">{{ $track->shortLabel() }}</span>
                    </span>
                    <span class="inline-flex min-w-[1.75rem] items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-200/80 text-gray-600' }}">
                        {{ en_num($count) }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    @if ($activeTrack && $activeMeta)
    <div class="mt-4 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="h-1 w-full" style="background: linear-gradient(90deg, {{ $activeMeta['gradient_from'] ?? $activeMeta['color'] ?? '#335483' }} 0%, {{ $activeMeta['gradient_to'] ?? $activeMeta['color'] ?? '#335483' }} 100%)"></div>
        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-right">
                <p class="text-sm font-bold" style="color:#111827">{{ $activeTrack->label() }}</p>
                <p class="mt-1 text-sm leading-relaxed" style="color:#6B7280">{{ $activeMeta['description'] ?? '' }}</p>
            </div>
            @if (! empty($activeMeta['focus']))
            <div class="flex flex-wrap justify-end gap-2 sm:max-w-md">
                @foreach ($activeMeta['focus'] as $item)
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" style="background: {{ ($activeMeta['color'] ?? '#335483') }}14; color: {{ $activeMeta['color'] ?? '#335483' }}">
                    {{ $item }}
                </span>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
