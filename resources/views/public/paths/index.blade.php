@extends('layouts.public')
@section('title', 'المسارات التعليمية')
@section('content')

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">المسارات التعليمية</h1>
    <p class="mt-2 text-gray-500 text-sm">استكشف المسارات المتاحة وسجّل في ما يناسبك.</p>
</div>

@if ($paths->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لا توجد مسارات منشورة حالياً.
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach ($paths as $path)
    <a href="{{ route('public.paths.show', $path->slug) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition block">
        <h3 class="font-semibold text-gray-800 mb-2">{{ $path->title }}</h3>
        <p class="text-sm text-gray-500 line-clamp-3">{{ $path->description }}</p>
        @if ($path->capacity)
        <p class="mt-3 text-xs text-gray-400">👥 سعة: {{ $path->capacity }}</p>
        @endif
    </a>
    @endforeach
</div>

@if ($paths->hasPages())
<div class="mt-8">{{ $paths->links() }}</div>
@endif
@endif

@endsection
