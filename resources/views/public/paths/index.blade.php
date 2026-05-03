@extends('layouts.public')
@section('title', 'المسارات التدريبية')
@section('content')

<div class="mb-8">
    <h1 class="text-3xl font-bold" style="color:#111827">المسارات التدريبية</h1>
    <p class="mt-2 text-sm" style="color:#6B7280">استكشف المسارات المتاحة وسجّل في ما يناسبك.</p>
</div>

@if ($paths->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center" style="color:#6B7280">
    لا توجد مسارات منشورة حالياً.
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach ($paths as $index => $path)
    <a href="{{ route('public.paths.show', $path->slug) }}" class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md
              hover:-translate-y-0.5 transition-all duration-300 block text-right overflow-hidden">

        <x-public.card-media
            variant="catalog"
            mediaContext="path"
            :hasImage="filled($path->image)"
            :imageUrl="$path->imagePublicUrl()"
            :alt="$path->title"
            :index="$index"
        />

        <div class="p-5">
            <h3 class="mb-2 font-semibold transition-colors group-hover:text-[#253B5B]" style="color:#111827">{{ $path->title }}</h3>
            <p class="line-clamp-3 text-sm" style="color:#6B7280">{{ $path->description }}</p>
            @if ($path->capacity)
            <p class="mt-3 flex items-center justify-end gap-1.5 text-xs" style="color:#6B7280">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                {{ $path->capacity }}
            </p>
            @endif
            <div class="mt-4 flex items-center justify-end gap-1.5 text-xs font-semibold" style="color:#253B5B">
                عرض المسار
                <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </div>
        </div>
    </a>
    @endforeach
</div>

@if ($paths->hasPages())
<div class="mt-8">{{ $paths->links() }}</div>
@endif
@endif

@endsection
