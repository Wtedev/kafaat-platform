@extends('layouts.public')

@section('title', $track->shortLabel().' — البرامج')
@section('meta_description', $meta['description'] ?? '')

@section('content')

@php $color = $meta['color'] ?? '#335483'; @endphp

<header class="mb-8">
  <a href="{{ route('public.tracks.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium transition hover:opacity-70" style="color:#335483">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    مسارات الكفاءة
  </a>
  <div class="flex items-start gap-4">
    <span class="mt-1 h-12 w-1 shrink-0 rounded-full" style="background:{{ $color }}"></span>
    <div class="min-w-0 text-right">
      <h1 class="text-2xl font-bold sm:text-3xl" style="color:#111827">{{ $track->label() }}</h1>
      <p class="mt-2 max-w-2xl text-sm leading-relaxed" style="color:#6B7280">{{ $meta['description'] ?? '' }}</p>
    </div>
  </div>
</header>

@if ($programs->isEmpty())
<div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
  <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl" style="background:{{ $color }}14">
    <svg class="h-6 w-6" style="color:{{ $color }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
    </svg>
  </span>
  <p class="font-semibold" style="color:#111827">لا توجد برامج في هذا المسار حالياً</p>
  <p class="mt-1.5 text-sm" style="color:#6B7280">جرّب مساراً آخر من قائمة البرامج في الأعلى.</p>
</div>
@else
<p class="mb-5 text-sm" style="color:#6B7280">
  <span class="font-bold tabular-nums" style="color:#111827">{{ en_num($programs->total()) }}</span> برنامج
</p>

<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
  @foreach ($programs as $index => $program)
  <a href="{{ route('public.programs.show', $program->slug) }}" class="group overflow-hidden rounded-2xl border border-gray-100 bg-white text-right shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">

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
      <h3 class="mb-2 font-semibold leading-snug transition-colors group-hover:text-[#335483]" style="color:#111827">{{ $program->title }}</h3>
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
<div class="mt-8">{{ $programs->links() }}</div>
@endif
@endif

@endsection
