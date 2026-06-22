@extends('layouts.auth')
@section('title', 'تحقق من بريدك الإلكتروني')
@section('content')

<div class="text-center mb-6">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4" style="background:#EAF2FA">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#253B5B">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
    </div>
    <h1 class="text-xl font-bold text-gray-900">تحقق من بريدك الإلكتروني</h1>
</div>

<p class="text-sm text-gray-600 text-center mb-6 leading-relaxed">
    أرسلنا إليك رابط تحقق على بريدك الإلكتروني.
    يرجى فتح بريدك والنقر على الرابط لتفعيل حسابك.
</p>

@if (session('status'))
<div class="mb-4 rounded-xl bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm text-center">
    {{ session('status') }}
</div>
@endif

<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit"
            class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition">
        إعادة إرسال رابط التحقق
    </button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-3">
    @csrf
    <button type="submit"
            class="w-full py-2.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 transition">
        تسجيل الخروج
    </button>
</form>

@endsection
