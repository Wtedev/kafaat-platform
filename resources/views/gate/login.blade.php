@extends('layouts.gate')

@section('title', 'دخول بوابة التحضير')

@section('content')
<div class="bg-white/95 rounded-3xl shadow-xl border border-white/80 p-7 sm:p-8">
    <h1 class="text-xl font-bold text-gray-900 text-center">دخول المتحضّرة</h1>
    <p class="mt-2 text-center text-sm text-gray-600 leading-relaxed">{{ $program->title }}</p>
    <p class="mt-1 text-center text-xs text-gray-500">أدخلي بريد الدعوة ورمز التحقق المرسل إلى بريدك.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('gate.login.store', ['program' => $program->slug]) }}" class="mt-6 space-y-4" novalidate>
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25"
            />
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">رمز التحقق</label>
            <input
                id="code"
                type="text"
                name="code"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                required
                autocomplete="one-time-code"
                class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm tracking-[0.35em] text-center font-semibold focus:outline-none focus:ring-2 focus:ring-brand/25"
            />
        </div>

        <button type="submit" class="w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
            دخول المسح
        </button>
    </form>
</div>
@endsection
