@extends('layouts.auth')
@section('title', 'التسجيل غير متاح')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">التسجيل غير متاح مؤقتاً</h1>

<p class="text-sm text-gray-600 text-center leading-relaxed">
    لا يمكن إنشاء حساب جديد في الوقت الحالي لعدم توفر سياسة الخصوصية الفعّالة.
    يرجى المحاولة لاحقاً أو التواصل مع الدعم.
</p>

<p class="mt-8 text-center text-sm text-gray-500">
    <a href="{{ route('login') }}" class="text-brand font-medium hover:underline">العودة لتسجيل الدخول</a>
</p>

@endsection
