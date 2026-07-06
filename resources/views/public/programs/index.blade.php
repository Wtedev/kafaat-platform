@extends('layouts.public')
@section('title', 'البرامج التدريبية')

@section('content')

<div class="mb-8 text-center">
    <h1 class="text-3xl font-bold" style="color:#111827">البرامج التدريبية</h1>
    <p class="mt-2 text-sm sm:text-base" style="color:#6B7280">اختر مسار الكفاءة المناسب لك أو استعرض جميع البرامج المتاحة.</p>
</div>

<x-public.program-track-tabs
    :activeTrack="$activeTrack"
    :programCounts="$programCounts"
    class="mb-8"
/>

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

@endsection
