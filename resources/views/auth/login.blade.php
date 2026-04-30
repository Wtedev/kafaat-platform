@extends('layouts.auth')
@section('title', 'تسجيل الدخول')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">تسجيل الدخول</h1>

<form method="POST" action="{{ route('login') }}" novalidate>
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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
            <input type="password" name="password" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <input type="checkbox" name="remember" id="remember" class="rounded border-gray-300 text-indigo-600" />
                <label for="remember" class="text-sm text-gray-600">تذكّرني</label>
            </div>
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline">نسيت كلمة المرور؟</a>
        </div>

    </div>

    <button type="submit" class="mt-6 w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition">
        دخول
    </button>

</form>

<p class="mt-6 text-center text-sm text-gray-500">
    ليس لديك حساب؟
    <a href="{{ route('register') }}" class="text-indigo-600 font-medium hover:underline">إنشاء حساب</a>
</p>

@endsection
