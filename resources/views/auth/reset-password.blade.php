@extends('layouts.auth')
@section('title', 'إعادة تعيين كلمة المرور')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">إعادة تعيين كلمة المرور</h1>

<form method="POST" action="{{ route('password.store') }}" novalidate>
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
        {{ $errors->first() }}
    </div>
    @endif

    <div class="space-y-4">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400
                       @error('email') border-red-400 @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
            <input type="password" name="password" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400
                       @error('password') border-red-400 @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
            <input type="password" name="password_confirmation" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

    </div>

    <button type="submit" class="mt-6 w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition">
        تعيين كلمة المرور
    </button>
</form>

@endsection
