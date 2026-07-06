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
@endphp

<nav {{ $attributes->merge(['class' => '']) }} aria-label="تصفية البرامج حسب مسار الكفاءة">
    <div class="overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex min-w-max items-center gap-2 sm:min-w-0 sm:flex-wrap sm:justify-start">
            <a href="{{ route('public.programs.index') }}"
               class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition {{ $activeTrack === null ? 'text-white shadow-sm' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}"
               @if ($activeTrack === null) style="background:#335483" @endif
               @if ($activeTrack === null) aria-current="page" @endif>
                جميع البرامج
                <span class="rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums {{ $activeTrack === null ? 'bg-white/20' : 'bg-gray-100 text-gray-500' }}">{{ en_num($totalCount) }}</span>
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
                   class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition {{ $isActive ? 'text-white shadow-sm' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}"
                   @if ($isActive) style="background:{{ $color }}" @endif
                   @if ($isActive) aria-current="page" @endif>
                    <span class="h-2 w-2 shrink-0 rounded-full {{ $isActive ? 'bg-white/90' : '' }}" @unless($isActive) style="background:{{ $color }}" @endunless></span>
                    {{ $track->shortLabel() }}
                    <span class="rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums {{ $isActive ? 'bg-white/20' : 'bg-gray-100 text-gray-500' }}">{{ en_num($count) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</nav>
