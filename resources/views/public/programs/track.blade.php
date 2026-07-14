@extends('layouts.public')

@section('title', $track->shortLabel().' — البرامج')
@section('meta_description', $meta['description'] ?? '')

@section('content')

@php $color = $meta['color'] ?? '#335483'; @endphp

<div class="mb-8 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="h-1.5 w-full" style="background:linear-gradient(90deg, {{ $meta['gradient_from'] ?? $color }} 0%, {{ $meta['gradient_to'] ?? $color }} 100%)"></div>
    <div class="px-5 py-6 sm:px-8 sm:py-7">
        <a href="{{ route('public.tracks.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium transition hover:opacity-70" style="color:#335483">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            مسارات الكفاءة
        </a>
        <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $track->label() }}</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed sm:text-base" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>
        @if (! empty($meta['stat_label']))
        <p class="mt-3 inline-flex rounded-lg px-3 py-1 text-xs font-semibold" style="background:{{ $color }}12; color:{{ $color }}">{{ $meta['stat_label'] }}</p>
        @endif
    </div>
</div>

@if ($programs->isEmpty())
<div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
    <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl" style="background:{{ $color }}12">
        <svg class="h-7 w-7" style="color:{{ $color }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
    </span>
    <p class="text-base font-semibold" style="color:#111827">لا توجد برامج في هذا المسار حالياً</p>
    <p class="mt-1.5 text-sm" style="color:#6B7280">اختر مساراً آخر من قائمة البرامج في الأعلى.</p>
</div>
@else
<p class="mb-5 text-sm" style="color:#6B7280">
    <span class="font-bold tabular-nums" style="color:#111827">{{ en_num($programs->total()) }}</span> برنامج متاح
</p>

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($programs as $index => $program)
    <a href="{{ route('public.programs.show', $program->slug) }}" class="group overflow-hidden rounded-2xl border border-gray-100 bg-white text-right shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">

        <x-public.card-media
            variant="catalog"
            mediaContext="program"
            :programKind="$program->program_kind"
            :hasImage="filled($program->image)"
            :imageUrl="$program->imagePublicUrl()"
            objectFit="cover"
            :alt="$program->title"
            :index="$index"
        />

        <div class="p-5">
            <h3 class="mb-2 font-bold leading-snug transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $program->title }}</h3>
            <p class="line-clamp-2 text-sm leading-relaxed" style="color:#6B7280">{{ $program->description }}</p>
            <div class="mt-4 flex items-center justify-end gap-1.5 text-xs font-semibold" style="color:#335483">
                عرض البرنامج
                <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>
    </a>
    @endforeach
</div>

@if ($programs->hasPages())
<div class="mt-10">{{ $programs->links() }}</div>
@endif
@endif

@endsection
