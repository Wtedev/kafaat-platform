@extends('layouts.public')

@section('title', 'مسارات الكفاءة — كفاءات')
@section('meta_description', 'تعرّف على مسارات الكفاءة الثلاثة في جمعية كفاءات: الذاتية، المهنية، والمجتمعية — وآلية الظهور البصري لكل مسار.')

@section('content')

@php
$intro = config('competency_tracks.intro', []);
$pdfPath = $intro['pdf_path'] ?? null;
$pdfUrl = $pdfPath && file_exists(public_path($pdfPath)) ? asset($pdfPath) : null;
@endphp

<div class="mb-8 text-center">
    <div class="mb-4 inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold" style="border-color:#c5d4e4; background:#e9eff6; color:#335483">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        {{ $intro['badge'] ?? 'مسارات العمل' }}
    </div>
    <h1 class="mb-3 text-3xl font-bold sm:text-4xl" style="color:#111827">{{ $intro['title'] ?? 'مسارات الكفاءة' }}</h1>
    <p class="mx-auto max-w-3xl text-base leading-relaxed" style="color:#6B7280">{{ $intro['subtitle'] ?? '' }}</p>
    @if ($pdfUrl)
    <div class="mt-6">
        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 rounded-2xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
           style="background:#335483">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            عرض دليل الظهور البصري (PDF)
        </a>
    </div>
    @endif
</div>

<x-public.competency-tracks-showcase :programCounts="$programCounts" class="mb-10" />

<div class="rounded-3xl border border-gray-100 bg-[#F8FAFC] p-6 sm:p-8">
    <h2 class="mb-4 text-lg font-bold" style="color:#111827">كيف ترتبط المسارات بالبرامج؟</h2>
    <p class="mb-4 text-sm leading-relaxed" style="color:#6B7280">
        كل برنامج أو مبادرة في الجمعية يندرج تحت أحد مسارات الكفاءة الثلاثة. عند تصفح البرامج يمكنك التصفية حسب المسار لاستكشاف ما يناسب اهتماماتك — سواء في التطوير الذاتي، الجاهزية المهنية، أو العمل المجتمعي.
    </p>
    <a href="{{ route('public.programs.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#335483">
        الانتقال إلى البرامج
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
</div>

@endsection
