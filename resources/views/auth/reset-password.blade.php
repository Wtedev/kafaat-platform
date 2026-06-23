@extends('layouts.auth')
@section('title', 'إعادة تعيين كلمة المرور')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">إعادة تعيين كلمة المرور</h1>

<form method="POST" action="{{ route('password.store') }}" novalidate>
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    @if ($errors->any())
    <div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
        {{ $errors->first() }}
    </div>
    @endif

    <div class="space-y-4">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25
                       @error('email') border-brand-danger @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
            <input type="password" name="password" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25
                       @error('password') border-brand-danger @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
            <input type="password" name="password_confirmation" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25" />
        </div>

    </div>

    <button type="submit" class="mt-6 w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
        تعيين كلمة المرور
    </button>
</form>

@endsection
