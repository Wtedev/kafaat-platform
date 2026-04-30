@extends('layouts.public')
@section('title', 'البرامج التدريبية')
@section('content')

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">البرامج التدريبية</h1>
    <p class="mt-2 text-gray-500 text-sm">اكتشف البرامج المتاحة واستثمر وقتك في تطوير مهاراتك.</p>
</div>

@if ($programs->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لا توجد برامج منشورة حالياً.
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach ($programs as $program)
    <a href="{{ route('public.programs.show', $program->slug) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition block">
        <h3 class="font-semibold text-gray-800 mb-2">{{ $program->title }}</h3>
        <p class="text-sm text-gray-500 line-clamp-3">{{ $program->description }}</p>
        <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-400">
            @if ($program->start_date)
            <span>📅 {{ $program->start_date->format('Y/m/d') }}</span>
            @endif
            @if ($program->capacity)
            <span>👥 {{ $program->capacity }}</span>
            @endif
        </div>
    </a>
    @endforeach
</div>

@if ($programs->hasPages())
<div class="mt-8">{{ $programs->links() }}</div>
@endif
@endif

@endsection
