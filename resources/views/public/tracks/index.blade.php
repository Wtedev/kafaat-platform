@extends('layouts.public')

@section('title', 'مسارات الكفاءة — كفاءات')
@section('meta_description', 'تعرّف على فكرة مسارات الكفاءة الثلاثة في جمعية كفاءات: الذاتية، المهنية، والمجتمعية — وكيف ترتبط ببرامج الجمعية.')

@section('content')

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$intro = config('competency_tracks.intro', []);
$about = config('competency_tracks.about', []);
$tracks = CompetencyTrackCatalog::tracks();
$order = CompetencyTrackCatalog::order();
$totalCount = (int) $programCounts->sum();
@endphp

<header class="mb-8 text-center sm:text-right">
    <p class="mb-2 text-sm font-semibold tracking-wide" style="color:#1a9399">{{ $intro['badge'] ?? 'مسارات الكفاءة' }}</p>
    <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h1>
    <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed sm:mx-0 sm:text-base" style="color:#6B7280">{{ $intro['subtitle'] ?? '' }}</p>
</header>

<div class="mb-8 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
    <h2 class="mb-2 text-lg font-bold" style="color:#111827">{{ $about['heading'] ?? 'ما المقصود بمسارات الكفاءة؟' }}</h2>
    <p class="text-sm leading-relaxed" style="color:#6B7280">{{ $about['body'] ?? '' }}</p>
</div>

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-5">
    @foreach ($order as $trackKey)
        @php
        $track = CompetencyTrack::from($trackKey);
        $meta = $tracks[$trackKey] ?? [];
        $color = $meta['color'] ?? '#335483';
        $count = (int) ($programCounts[$trackKey] ?? 0);
        @endphp
        <article class="flex h-full flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold tabular-nums" style="background:{{ $color }}14; color:{{ $color }}">
                    {{ en_num($count) }} برنامج
                </span>
                <span class="flex h-11 w-11 items-center justify-center rounded-xl" style="background:{{ $color }}14">
                    <span class="h-3 w-3 rounded-full" style="background:{{ $color }}"></span>
                </span>
            </div>
            <h3 class="mb-2 text-lg font-bold leading-snug" style="color:#111827">{{ $track->label() }}</h3>
            <p class="mb-4 text-sm leading-relaxed" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>
            @if (! empty($meta['focus']))
            <ul class="mb-5 space-y-2 border-t border-gray-100 pt-4">
                @foreach ($meta['focus'] as $item)
                <li class="flex items-center justify-end gap-2 text-xs" style="color:#6B7280">
                    <span>{{ $item }}</span>
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background:{{ $color }}"></span>
                </li>
                @endforeach
            </ul>
            @endif
            <a href="{{ route('public.programs.index', ['track' => $trackKey]) }}"
               class="mt-auto inline-flex items-center justify-end gap-1.5 text-sm font-semibold transition hover:opacity-80"
               style="color:{{ $color }}">
                استكشف برامج {{ $track->shortLabel() }}
                <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </article>
    @endforeach
</div>

<div class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-[#F8FAFC] p-5 sm:flex-row sm:p-6">
    <div class="text-center sm:text-right">
        <h2 class="text-base font-bold" style="color:#111827">{{ $about['relation_heading'] ?? 'كيف ترتبط بالبرامج؟' }}</h2>
        <p class="mt-1.5 max-w-xl text-sm leading-relaxed" style="color:#6B7280">{{ $about['relation_body'] ?? '' }}</p>
        <p class="mt-2 text-sm" style="color:#6B7280">
            <span class="font-bold tabular-nums" style="color:#111827">{{ en_num($totalCount) }}</span>
            برنامج منشور عبر المسارات الثلاثة
        </p>
    </div>
    <a href="{{ route('public.programs.index') }}"
       class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:shadow-md"
       style="background:#335483">
        تصفح البرامج
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

@endsection
