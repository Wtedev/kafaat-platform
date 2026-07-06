@extends('layouts.public')
@section('title', 'البرامج التدريبية')

@section('content')

@php
use App\Enums\CompetencyTrack;
use App\Support\CompetencyTrackCatalog;

$trackOrder = CompetencyTrackCatalog::order();
$otherTracks = $activeTrack
    ? collect($trackOrder)->reject(fn ($key) => $key === $activeTrack->value)
    : collect();
@endphp

<header class="mb-8 text-center sm:text-right">
    <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">البرامج التدريبية</h1>
    <p class="mt-2 text-sm" style="color:#6B7280">اختر مسار الكفاءة المناسب أو استعرض جميع البرامج المتاحة.</p>
</header>

<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-4 py-4 sm:px-6">
        <x-public.competency-tracks-section
            variant="filter"
            :activeTrack="$activeTrack"
            :programCounts="$programCounts"
        />
    </div>

    <div class="p-4 sm:p-6">
        @if ($programs->isEmpty())
        <div class="flex flex-col items-center px-4 py-14 text-center sm:py-16">
            <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl" style="background:#e9eff6">
                <svg class="h-7 w-7" style="color:#335483" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </span>
            <p class="text-base font-semibold" style="color:#111827">
                @if ($activeTrack)
                    لا توجد برامج في {{ $activeTrack->shortLabel() }} حالياً
                @else
                    لا توجد برامج منشورة حالياً
                @endif
            </p>
            <p class="mt-1.5 max-w-md text-sm leading-relaxed" style="color:#6B7280">
                @if ($activeTrack)
                    جرّب مساراً آخر أو اعرض جميع البرامج المتاحة في الجمعية.
                @else
                    سيتم نشر البرامج الجديدة هنا فور اعتمادها.
                @endif
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                @if ($activeTrack)
                <a href="{{ route('public.programs.index') }}"
                   class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:shadow-md"
                   style="background:#335483">
                    عرض جميع البرامج
                </a>
                @foreach ($otherTracks as $trackKey)
                    @php $other = CompetencyTrack::from($trackKey); @endphp
                    <a href="{{ route('public.programs.index', ['track' => $trackKey]) }}"
                       class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900">
                        {{ $other->shortLabel() }}
                    </a>
                @endforeach
                @endif
            </div>
        </div>
        @else
        <div class="mb-4 flex items-center justify-between gap-3 text-sm" style="color:#6B7280">
            <span>
                <span class="font-bold tabular-nums" style="color:#111827">{{ en_num($programs->total()) }}</span>
                برنامج
                @if ($activeTrack)
                    في {{ $activeTrack->shortLabel() }}
                @endif
            </span>
            @if ($activeTrack)
            <a href="{{ route('public.programs.index') }}" class="font-semibold transition hover:text-[#335483]">عرض الكل</a>
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($programs as $index => $program)
            <a href="{{ route('public.programs.show', $program->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-100 bg-[#FAFBFC] text-right transition hover:border-gray-200 hover:bg-white hover:shadow-md">

                <x-public.card-media
                    variant="catalog"
                    mediaContext="program"
                    :programKind="$program->program_kind"
                    :hasImage="filled($program->image)"
                    :imageUrl="$program->imagePublicUrl()"
                    :alt="$program->title"
                    :index="$index"
                />

                <div class="flex flex-1 flex-col p-4 sm:p-5">
                    @if ($program->competency_track)
                    @php $tMeta = config('competency_tracks.tracks.'.$program->competency_track->value, []); @endphp
                    <span class="mb-2 self-end inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold text-white" style="background:{{ $tMeta['color'] ?? '#335483' }}">
                        {{ $program->competency_track->shortLabel() }}
                    </span>
                    @endif
                    <h3 class="mb-2 font-semibold leading-snug transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $program->title }}</h3>
                    <p class="line-clamp-2 flex-1 text-sm leading-relaxed" style="color:#6B7280">{{ $program->description }}</p>
                    <div class="mt-4 flex items-center justify-end gap-1.5 text-xs font-semibold" style="color:#335483">
                        عرض البرنامج
                        <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        @if ($programs->hasPages())
        <div class="mt-8 border-t border-gray-100 pt-6">{{ $programs->links() }}</div>
        @endif
        @endif
    </div>
</div>

@endsection
