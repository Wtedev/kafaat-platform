@extends('layouts.auth')
@section('title', 'نسيت كلمة المرور')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-2">نسيت كلمة المرور؟</h1>
<p class="text-sm text-gray-500 text-center mb-6">أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة التعيين.</p>

@if (session('status'))
<div class="mb-5 rounded-xl bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm text-center">
    {{ session('status') }}
</div>
@endif

<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf

    @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
        {{ $errors->first() }}
    </div>
    @endif

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400
                       @error('email') border-red-400 @enderror" />
        </div>
    </div>

    <button type="submit" class="mt-6 w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition">
        إرسال رابط إعادة التعيين
    </button>
</form>

<p class="mt-6 text-center text-sm text-gray-500">
    تذكّرت كلمة المرور؟
    <a href="{{ route('login') }}" class="text-indigo-600 font-medium hover:underline">تسجيل الدخول</a>
</p>

@endsection
