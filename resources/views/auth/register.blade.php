@extends('layouts.auth')
@section('title', 'إنشاء حساب')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">إنشاء حساب جديد</h1>

<form method="POST" action="{{ route('register') }}" novalidate>
    @csrf

    @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="space-y-4">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400
                              @error('name') border-red-400 @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400
                              @error('email') border-red-400 @enderror" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
            <input type="password" name="password" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
            <input type="password" name="password_confirmation" required class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>

    </div>

    <p class="mt-4 text-xs text-gray-400">
        يُنشأ الحساب تلقائياً كحساب مستفيد.
    </p>

    <button type="submit" class="mt-5 w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 transition">
        إنشاء الحساب
    </button>

</form>

<p class="mt-6 text-center text-sm text-gray-500">
    لديك حساب بالفعل؟
    <a href="{{ route('login') }}" class="text-indigo-600 font-medium hover:underline">تسجيل الدخول</a>
</p>

@endsection
