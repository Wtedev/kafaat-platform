@extends('layouts.public')
@section('title', 'البرامج التدريبية')

@section('content')

<header class="mb-8">
    <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">البرامج التدريبية</h1>
    <p class="mt-1.5 text-sm" style="color:#6B7280">تصفّح برامج الجمعية حسب مسار الكفاءة.</p>
</header>

<div class="mb-8 rounded-2xl border border-gray-100 bg-white px-4 shadow-sm sm:px-5">
    <x-public.competency-tracks-section
        variant="filter"
        :activeTrack="$activeTrack"
        :programCounts="$programCounts"
        class="py-1"
    />
</div>

@if ($programs->isEmpty())
<div class="rounded-xl border border-dashed border-gray-200 bg-[#F8FAFC] px-6 py-14 text-center text-sm" style="color:#6B7280">
    لا توجد برامج منشورة{{ $activeTrack ? ' في هذا المسار' : '' }} حالياً.
</div>
@else
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($programs as $index => $program)
    <a href="{{ route('public.programs.show', $program->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white text-right shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">

        <x-public.card-media
            variant="catalog"
            mediaContext="program"
            :programKind="$program->program_kind"
            :hasImage="filled($program->image)"
            :imageUrl="$program->imagePublicUrl()"
            :alt="$program->title"
            :index="$index"
        />

        <div class="flex flex-1 flex-col p-5">
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
<div class="mt-8">{{ $programs->links() }}</div>
@endif
@endif

@endsection
