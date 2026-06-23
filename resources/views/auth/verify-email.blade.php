@extends('layouts.auth')
@section('title', 'تحقق من بريدك الإلكتروني')
@section('content')

<div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background:#e9eff6">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#335483">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </div>
    <h1 class="text-xl font-bold text-gray-900">تحقق من بريدك الإلكتروني</h1>
</div>

<p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
    أرسلنا رمز تحقق مكوّناً من 6 أرقام إلى بريدك الإلكتروني.
    أدخل الرمز أدناه لتفعيل حسابك.
</p>

@if (session('status'))
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_success') }} px-4 py-3 text-sm text-center">
    {{ session('status') }}
</div>
@endif

@error('code')
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm text-center">
    {{ $message }}
</div>
@enderror

<form method="POST" action="{{ route('verification.verify') }}">
    @csrf
    <label for="code" class="sr-only">رمز التحقق</label>
    <input type="text"
           id="code"
           name="code"
           inputmode="numeric"
           autocomplete="one-time-code"
           pattern="[0-9]{6}"
           maxlength="6"
           required
           autofocus
           placeholder="000000"
           class="w-full mb-4 py-3 rounded-xl border border-gray-200 text-center text-2xl font-bold tracking-[0.5em] text-gray-900 focus:border-brand focus:ring-2 focus:ring-brand/20 outline-none"
           dir="ltr">
    <button type="submit"
            class="w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
        تأكيد الرمز
    </button>
</form>

<form method="POST" action="{{ route('verification.send') }}" class="mt-3">
    @csrf
    <button type="submit"
            class="w-full py-2.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 transition">
        إعادة إرسال الرمز
    </button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-3">
    @csrf
    <button type="submit"
            class="w-full py-2.5 rounded-xl text-gray-500 font-medium text-sm hover:text-gray-700 transition">
        تسجيل الخروج
    </button>
</form>

@endsection
