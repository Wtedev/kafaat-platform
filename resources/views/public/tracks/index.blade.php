@extends('layouts.public')

@section('title', 'مسارات الكفاءة — كفاءات')
@section('meta_description', 'مسارات الكفاءة الثلاثة في جمعية كفاءات: الذاتية، المهنية، والمجتمعية.')

@section('content')

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$intro = config('competency_tracks.intro', []);
$about = config('competency_tracks.about', []);
$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
@endphp

<header class="mb-8 max-w-2xl text-right">
    <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h1>
    <p class="mt-3 text-sm leading-relaxed sm:text-base" style="color:#6B7280">{{ $about['body'] ?? $intro['subtitle'] ?? '' }}</p>
</header>

<ul class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm divide-y divide-gray-100">
    @foreach ($order as $trackKey)
        @php
        $track = CompetencyTrack::from($trackKey);
        $meta = $tracks[$trackKey] ?? [];
        $color = $meta['color'] ?? '#335483';
        $count = (int) ($programCounts[$trackKey] ?? 0);
        @endphp
        <li>
            <a href="{{ route('public.programs.track', $track) }}"
               class="group flex items-center gap-4 px-5 py-4 transition hover:bg-[#F8FAFC] sm:px-6 sm:py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:{{ $color }}14">
                    <span class="h-2.5 w-2.5 rounded-full" style="background:{{ $color }}"></span>
                </span>
                <div class="min-w-0 flex-1 text-right">
                    <p class="font-bold transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $track->shortLabel() }}</p>
                    <p class="mt-0.5 line-clamp-1 text-sm" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden text-xs font-semibold tabular-nums sm:inline" style="color:#9CA3AF">{{ en_num($count) }} برنامج</span>
                    <svg class="h-4 w-4 rotate-180 text-gray-300 transition group-hover:text-[#335483]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
        </li>
    @endforeach
</ul>

@endsection
