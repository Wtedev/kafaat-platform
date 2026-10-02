@extends('layouts.auth')
@section('title', 'التحقق من الشهادة')
@section('content')

@php
    $found = (bool) ($display['found'] ?? false);
    $revoked = (bool) ($display['revoked'] ?? false);
    $heading = ! $found ? 'الشهادة غير صالحة' : ($revoked ? 'هذه الشهادة ملغاة' : 'شهادة صحيحة ✓');
@endphp

<div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-full {{ $found && ! $revoked ? 'bg-[#e6f5f6]' : 'bg-[#fdeeed]' }} mb-4">
        @if($found && ! $revoked)
        <svg class="w-7 h-7 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        @else
        <svg class="w-7 h-7 text-brand-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        @endif
    </div>
    <h1 class="text-xl font-bold {{ $found && ! $revoked ? 'text-brand-secondary' : 'text-brand-danger' }}">
        {{ $heading }}
    </h1>
</div>

@if($found)
<div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden text-sm">
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50">
        <span class="text-gray-500">اسم المستفيد</span>
        <span class="font-semibold text-gray-800">{{ $display['name'] ?: '—' }}</span>
    </div>
    <div class="flex items-center justify-between px-4 py-3">
        <span class="text-gray-500">النشاط</span>
        <span class="font-semibold text-gray-800">{{ $display['activity'] ?: '—' }}</span>
    </div>
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50">
        <span class="text-gray-500">رقم الشهادة</span>
        <span class="font-mono text-gray-700">{{ $display['number'] }}</span>
    </div>
    <div class="flex items-center justify-between px-4 py-3">
        <span class="text-gray-500">تاريخ الإصدار</span>
        <span class="text-gray-700">{{ $display['issuedAt'] ?: '—' }}</span>
    </div>
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50">
        <span class="text-gray-500">الحالة</span>
        <span class="font-semibold {{ $revoked ? 'text-brand-danger' : 'text-brand-secondary' }}">{{ $display['status'] }}</span>
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
    <a href="{{ route('home') }}" class="text-sm text-brand hover:underline">← العودة للرئيسية</a>
</div>

@endsection
