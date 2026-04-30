@extends('layouts.public')
@section('title', 'الفرص التطوعية')
@section('content')

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">الفرص التطوعية</h1>
    <p class="mt-2 text-gray-500 text-sm">أسهم في خدمة مجتمعك من خلال فرص تطوعية متنوعة.</p>
</div>

@if ($opportunities->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center text-gray-400">
    لا توجد فرص تطوعية منشورة حالياً.
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach ($opportunities as $opp)
    <a href="{{ route('public.volunteering.show', $opp->slug) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition block">
        <h3 class="font-semibold text-gray-800 mb-2">{{ $opp->title }}</h3>
        <p class="text-sm text-gray-500 line-clamp-3">{{ $opp->description }}</p>
        <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-400">
            @if ($opp->hours_expected)
            <span>⏱ {{ number_format((float)$opp->hours_expected, 0) }} ساعة</span>
            @endif
            @if ($opp->capacity)
            <span>👥 {{ $opp->capacity }}</span>
            @endif
            @if ($opp->start_date)
            <span>📅 {{ $opp->start_date->format('Y/m/d') }}</span>
            @endif
        </div>
    </a>
    @endforeach
</div>

@if ($opportunities->hasPages())
<div class="mt-8">{{ $opportunities->links() }}</div>
@endif
@endif

@endsection
