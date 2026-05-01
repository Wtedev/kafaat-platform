@extends('layouts.public')
@section('title', 'الفرص التطوعية')
@section('content')

<div class="mb-8">
    <h1 class="text-3xl font-bold" style="color:#111827">الفرص التطوعية</h1>
    <p class="mt-2 text-sm" style="color:#6B7280">أسهم في خدمة مجتمعك من خلال فرص تطوعية متنوعة ذات أثر حقيقي.</p>
</div>

@if ($opportunities->isEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center" style="color:#6B7280">
    لا توجد فرص تطوعية منشورة حالياً.
</div>
@else
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @foreach ($opportunities as $opp)
    <a href="{{ route('public.volunteering.show', $opp->slug) }}" class="group bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md
              hover:-translate-y-0.5 transition-all duration-300 block text-right">
        <h3 class="font-semibold mb-2 group-hover:text-[#253B5B] transition-colors" style="color:#111827">{{ $opp->title }}</h3>
        <p class="text-sm line-clamp-3" style="color:#6B7280">{{ $opp->description }}</p>
        <div class="mt-3 flex flex-wrap gap-3 text-xs" style="color:#6B7280">
            @if ($opp->hours_expected)
            <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ number_format((float)$opp->hours_expected, 0) }} ساعة
            </span>
            @endif
            @if ($opp->capacity)
            <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                {{ $opp->capacity }}
            </span>
            @endif
            @if ($opp->start_date)
            <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                {{ $opp->start_date->format('Y/m/d') }}
            </span>
            @endif
        </div>
        <div class="mt-4 text-xs font-semibold flex items-center gap-1.5 justify-end" style="color:#253B5B">
            قدّم طلبك
            <svg class="w-3.5 h-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </div>
    </a>
    @endforeach
</div>

@if ($opportunities->hasPages())
<div class="mt-8">{{ $opportunities->links() }}</div>
@endif
@endif

@endsection
