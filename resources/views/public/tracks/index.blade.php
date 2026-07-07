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

<header class="mb-10 max-w-2xl text-center sm:mx-0 sm:text-right">
    <p class="mb-2 text-sm font-semibold uppercase tracking-widest" style="color:#1a9399">{{ $intro['badge'] ?? 'مسارات الكفاءة' }}</p>
    <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h1>
    <p class="mt-3 text-sm leading-relaxed sm:text-base" style="color:#6B7280">{{ $about['body'] ?? '' }}</p>
</header>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
    @foreach ($order as $trackKey)
        @php
        $track = CompetencyTrack::from($trackKey);
        $meta = $tracks[$trackKey] ?? [];
        $color = $meta['color'] ?? '#335483';
        $count = (int) ($programCounts[$trackKey] ?? 0);
        @endphp
        <a href="{{ route('public.programs.track', $track) }}"
           class="group relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-5 text-right shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
            <div class="absolute inset-x-0 top-0 h-1" style="background:{{ $color }}"></div>
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl" style="background:{{ $color }}14">
                <span class="h-2.5 w-2.5 rounded-full" style="background:{{ $color }}"></span>
            </div>
            <h2 class="mb-2 font-bold" style="color:#111827">{{ $track->shortLabel() }}</h2>
            <p class="mb-4 line-clamp-2 text-sm leading-relaxed" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>
            <div class="flex items-center justify-between gap-2">
                <svg class="h-4 w-4 rotate-180 text-gray-300 transition group-hover:text-[#335483]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-xs font-semibold tabular-nums" style="color:{{ $color }}">{{ en_num($count) }} برنامج</span>
            </div>
        </a>
    @endforeach
</div>

@endsection
