@extends('layouts.gate')

@section('title', 'دخول بوابة التحضير')

@section('content')
<div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-7 sm:p-8">
    <h1 class="text-xl font-bold text-gray-900 text-center">بوابة التحضير</h1>
    <p class="mt-2 text-center text-sm text-gray-600 leading-relaxed">{{ $program->title }}</p>
    <p class="mt-3 text-center text-sm text-gray-500 leading-relaxed">
        الدخول عبر رابط التحضير الخاص بمسؤول التحضير فقط.
        افتح الرابط الذي استلمته من الإدارة للوصول إلى الصفحة.
    </p>

    @if ($errors->any())
        <div class="mt-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
