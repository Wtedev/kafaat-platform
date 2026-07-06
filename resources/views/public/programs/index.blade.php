@extends('layouts.public')
@section('title', 'البرامج التدريبية')

@section('content')

<div class="mb-6 border-b border-gray-100 pb-6">
    <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">البرامج التدريبية</h1>
    <p class="mt-1.5 text-sm" style="color:#6B7280">تصفّح البرامج حسب مسار الكفاءة أو اعرض الكل.</p>
    <x-public.program-track-tabs
        :activeTrack="$activeTrack"
        :programCounts="$programCounts"
        class="mt-5"
    />
</div>

@if ($programs->isEmpty())
<div class="rounded-xl border border-dashed border-gray-200 bg-[#F8FAFC] px-6 py-14 text-center text-sm" style="color:#6B7280">
    لا توجد برامج منشورة{{ $activeTrack ? ' في هذا المسار' : '' }} حالياً.
</div>
@else
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($programs as $index => $program)
    <a href="{{ route('public.programs.show', $program->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-xl border border-gray-100 bg-white text-right transition hover:border-gray-200 hover:shadow-md">

        <x-public.card-media
            variant="catalog"
            mediaContext="program"
            :programKind="$program->program_kind"
            :hasImage="filled($program->image)"
            :imageUrl="$program->imagePublicUrl()"
            :alt="$program->title"
            :index="$index"
        />

        <div class="flex flex-1 flex-col p-4">
            @if ($program->competency_track)
            @php $tMeta = config('competency_tracks.tracks.'.$program->competency_track->value, []); @endphp
            <span class="mb-2 self-end inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold text-white" style="background:{{ $tMeta['color'] ?? '#335483' }}">
                {{ $program->competency_track->shortLabel() }}
            </span>
            @endif
            <h3 class="mb-1.5 font-semibold leading-snug transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $program->title }}</h3>
            <p class="line-clamp-2 flex-1 text-sm leading-relaxed" style="color:#6B7280">{{ $program->description }}</p>
        </div>
    </a>
    @endforeach
</div>

@if ($programs->hasPages())
<div class="mt-8">{{ $programs->links() }}</div>
@endif
@endif

@endsection
