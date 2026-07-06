@extends('layouts.public')
@section('title', 'البرامج التدريبية')

@section('content')

@php
use App\Enums\CompetencyTrack;
@endphp

<div class="mb-8 text-center">
    <h1 class="text-3xl font-bold" style="color:#111827">البرامج التدريبية</h1>
    <p class="mt-2 text-sm sm:text-base" style="color:#6B7280">برامجنا منظّمة ضمن ثلاثة مسارات للكفاءة — اختر المسار الذي يناسبك أو استكشف الكل.</p>
</div>

<x-public.competency-tracks-showcase
    :programCounts="$programCounts"
    :activeTrack="$activeTrack"
    compact
    class="mb-10"
/>

<div class="mb-6 flex flex-wrap items-center justify-center gap-2">
    <a href="{{ route('public.programs.index') }}"
       class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $activeTrack === null ? 'text-white shadow-sm' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}"
       @if ($activeTrack === null) style="background:#335483" @endif>
        جميع البرامج
    </a>
    @foreach (CompetencyTrack::cases() as $track)
        @php $meta = config('competency_tracks.tracks.'.$track->value, []); @endphp
        <a href="{{ route('public.programs.index', ['track' => $track->value]) }}"
           class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $activeTrack === $track ? 'text-white shadow-sm' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}"
           @if ($activeTrack === $track) style="background:{{ $meta['color'] ?? '#335483' }}" @endif>
            {{ $track->shortLabel() }}
        </a>
    @endforeach
</div>

@if ($activeTrack)
<div class="mb-6 rounded-2xl border border-gray-100 bg-white px-5 py-4 text-center text-sm" style="color:#6B7280">
    تعرض الآن برامج <span class="font-bold" style="color:#111827">{{ $activeTrack->label() }}</span>
</div>
@endif

@if ($programs->isEmpty())
<div class="rounded-2xl border border-gray-100 bg-white p-12 text-center shadow-sm" style="color:#6B7280">
  لا توجد برامج منشورة{{ $activeTrack ? ' في هذا المسار' : '' }} حالياً.
</div>
@else
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($programs as $index => $program)
    <a href="{{ route('public.programs.show', $program->slug) }}" class="group block overflow-hidden rounded-2xl border border-gray-100 bg-white text-right shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md">

        <x-public.card-media
            variant="catalog"
            mediaContext="program"
            :programKind="$program->program_kind"
            :hasImage="filled($program->image)"
            :imageUrl="$program->imagePublicUrl()"
            :alt="$program->title"
            :index="$index"
        />

        <div class="p-5">
            @if ($program->competency_track)
            @php $tMeta = config('competency_tracks.tracks.'.$program->competency_track->value, []); @endphp
            <span class="mb-2 inline-flex rounded-lg px-2.5 py-1 text-[11px] font-bold text-white" style="background:{{ $tMeta['color'] ?? '#335483' }}">
                {{ $program->competency_track->shortLabel() }}
            </span>
            @endif
            <h3 class="mb-2 font-semibold transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $program->title }}</h3>
            <p class="line-clamp-3 text-sm" style="color:#6B7280">{{ $program->description }}</p>
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
<div class="mt-8">{{ $programs->links() }}</div>
@endif
@endif

<div class="mt-10 text-center">
    <a href="{{ route('public.tracks.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#335483">
        تعرّف أكثر على مسارات الكفاءة
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

@endsection
