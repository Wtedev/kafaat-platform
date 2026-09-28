@extends('layouts.public')

@section('title', 'البرامج')
@section('meta_description', 'تصفّح جميع البرامج التدريبية المتاحة في منصة كفاءات.')

@section('content')

<div class="mb-8 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="h-1.5 w-full" style="background:linear-gradient(90deg, #335483 0%, #1a9399 100%)"></div>
    <div class="px-5 py-6 sm:px-8 sm:py-7">
        <a href="{{ route('public.tracks.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium transition hover:opacity-70" style="color:#335483">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            مسارات الكفاءة
        </a>
        <h1 class="text-2xl font-bold sm:text-3xl" style="color:#335483">جميع البرامج</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed sm:text-base" style="color:#6B7280">برامج الجمعية التدريبية في مكان واحد، دون تقسيم حسب المسارات.</p>
    </div>
</div>

@if ($programs->isEmpty())
<div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
    <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl" style="background:#33548312">
        <svg class="h-7 w-7" style="color:#335483" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
    </span>
    <p class="text-base font-semibold" style="color:var(--brand-body)">لا توجد برامج منشورة حالياً</p>
    <p class="mt-1.5 text-sm" style="color:#6B7280">تابعنا لاحقاً للاطلاع على البرامج الجديدة.</p>
</div>
@else
<p class="mb-5 text-sm" style="color:#6B7280">
    <span class="font-bold tabular-nums" style="color:var(--brand-body)">{{ en_num($programs->total()) }}</span> برنامج متاح
</p>

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($programs as $index => $program)
    <x-public.program-catalog-card :program="$program" :index="$index" />
    @endforeach
</div>

@if ($programs->hasPages())
<div class="mt-10">{{ $programs->links() }}</div>
@endif
@endif

@endsection
