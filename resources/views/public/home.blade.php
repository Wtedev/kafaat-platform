@extends('layouts.public')
@section('title', 'كفاءات — منصة التدريب والتطوع')
@section('content')

{{-- Hero --}}
<section class="text-center py-16">
    <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 leading-tight">
        طوّر نفسك، <span class="text-indigo-600">أثّر في مجتمعك</span>
    </h1>
    <p class="mt-4 text-lg text-gray-500 max-w-xl mx-auto">
        انضم إلى مسارات تعليمية وبرامج تدريبية وفرص تطوعية تُحدث فارقاً حقيقياً.
    </p>
    @guest
    <a href="{{ route('login') }}" class="mt-8 inline-block px-8 py-3 rounded-2xl bg-indigo-600 text-white font-semibold text-base hover:bg-indigo-700 transition shadow-sm">
        ابدأ الآن
    </a>
    @endguest
</section>

{{-- Learning Paths --}}
<section class="mb-14">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl font-bold text-gray-800">المسارات التعليمية</h2>
        <a href="{{ route('public.paths.index') }}" class="text-sm text-indigo-600 hover:underline">عرض الكل</a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($paths as $path)
        <a href="{{ route('public.paths.show', $path->slug) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition block">
            <h3 class="font-semibold text-gray-800 mb-2">{{ $path->title }}</h3>
            <p class="text-sm text-gray-500 line-clamp-2">{{ $path->description }}</p>
        </a>
        @empty
        <p class="text-sm text-gray-400 col-span-3">لا توجد مسارات منشورة حالياً.</p>
        @endforelse
    </div>
</section>

{{-- Training Programs --}}
<section class="mb-14">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl font-bold text-gray-800">البرامج التدريبية</h2>
        <a href="{{ route('public.programs.index') }}" class="text-sm text-indigo-600 hover:underline">عرض الكل</a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($programs as $program)
        <a href="{{ route('public.programs.show', $program->slug) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition block">
            <h3 class="font-semibold text-gray-800 mb-2">{{ $program->title }}</h3>
            <p class="text-sm text-gray-500 line-clamp-2">{{ $program->description }}</p>
            @if ($program->start_date)
            <p class="mt-3 text-xs text-gray-400">📅 {{ $program->start_date->format('Y/m/d') }}</p>
            @endif
        </a>
        @empty
        <p class="text-sm text-gray-400 col-span-3">لا توجد برامج منشورة حالياً.</p>
        @endforelse
    </div>
</section>

{{-- Volunteer Opportunities --}}
<section>
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-xl font-bold text-gray-800">الفرص التطوعية</h2>
        <a href="{{ route('public.volunteering.index') }}" class="text-sm text-indigo-600 hover:underline">عرض الكل</a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($opportunities as $opp)
        <a href="{{ route('public.volunteering.show', $opp->slug) }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition block">
            <h3 class="font-semibold text-gray-800 mb-2">{{ $opp->title }}</h3>
            <p class="text-sm text-gray-500 line-clamp-2">{{ $opp->description }}</p>
            @if ($opp->hours_expected)
            <p class="mt-3 text-xs text-gray-400">⏱ {{ number_format((float)$opp->hours_expected, 0) }} ساعة</p>
            @endif
        </a>
        @empty
        <p class="text-sm text-gray-400 col-span-3">لا توجد فرص تطوعية منشورة حالياً.</p>
        @endforelse
    </div>
</section>

@endsection
