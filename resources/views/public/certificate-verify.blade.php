@extends('layouts.auth')
@section('title', 'التحقق من الشهادة')
@section('content')

<div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full {{ $certificate ? 'bg-green-100' : 'bg-red-100' }} mb-4">
        @if($certificate)
        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        @else
        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        @endif
    </div>
    <h1 class="text-xl font-bold {{ $certificate ? 'text-green-700' : 'text-red-600' }}">
        {{ $certificate ? 'شهادة صحيحة ✓' : 'الشهادة غير صالحة' }}
    </h1>
</div>

@if($certificate)
<div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden text-sm">
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50">
        <span class="text-gray-500">اسم المستفيد</span>
        <span class="font-semibold text-gray-800">{{ $certificate->user->name }}</span>
    </div>
    <div class="flex items-center justify-between px-4 py-3">
        <span class="text-gray-500">الموضوع</span>
        <span class="font-semibold text-gray-800">
            {{ $certificate->certificateable?->title ?? '—' }}
        </span>
    </div>
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50">
        <span class="text-gray-500">رقم الشهادة</span>
        <span class="font-mono text-gray-700">{{ $certificate->certificate_number }}</span>
    </div>
    <div class="flex items-center justify-between px-4 py-3">
        <span class="text-gray-500">تاريخ الإصدار</span>
        <span class="text-gray-700">{{ $certificate->issued_at->format('Y/m/d') }}</span>
    </div>
</div>

<p class="mt-5 text-center text-xs text-gray-400">
    تم إصدار هذه الشهادة من قِبَل جمعية كفاءات للتدريب والتطوير المهني.
</p>
@else
<p class="text-center text-gray-500 text-sm mt-2">
    رمز التحقق المدخل غير موجود في سجلاتنا.<br>
    يُرجى التأكد من الرمز والمحاولة مجدداً.
</p>
@endif

<div class="mt-6 text-center">
    <a href="{{ route('home') }}" class="text-sm text-indigo-600 hover:underline">← العودة للرئيسية</a>
</div>

@endsection
