@extends('layouts.auth')
@section('title', 'تسجيل الدخول')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">تسجيل الدخول</h1>

{{-- رسالة نجاح إعادة تعيين كلمة المرور --}}
@if (session('status'))
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_success') }} px-4 py-3 text-sm">
    {{ session('status') }}
</div>
@endif

{{-- رسائل الخطأ --}}
@if ($errors->any())
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
    <ul class="list-disc list-inside space-y-1">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    <div class="space-y-4">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25
                              @error('email') border-brand-danger @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
            <input type="password" name="password" required
                   class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25" />
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <input type="checkbox" name="remember" id="remember"
                       class="rounded border-gray-300 text-brand"
                       {{ old('remember') ? 'checked' : '' }} />
                <label for="remember" class="text-sm text-gray-600">تذكّرني</label>
            </div>
            <a href="{{ route('password.request') }}" class="text-sm text-brand hover:underline">نسيت كلمة المرور؟</a>
        </div>

    </div>

    <button type="submit" class="mt-6 w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
        دخول
    </button>

</form>

<p class="mt-6 text-center text-sm text-gray-500">
    ليس لديك حساب؟
    <a href="{{ route('register') }}" class="text-brand font-medium hover:underline">إنشاء حساب</a>
</p>

@endsection
