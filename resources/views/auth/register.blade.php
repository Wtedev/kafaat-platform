@extends('layouts.auth')
@section('title', 'إنشاء حساب')
@section('content')

<h1 class="text-xl font-bold text-gray-900 text-center mb-6">إنشاء حساب جديد</h1>

<form method="POST" action="{{ route('register') }}" novalidate>
    @csrf

    @if ($errors->any())
    <div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="space-y-6">
        <div>
            <h2 class="mb-3 text-sm font-bold text-gray-800">الاسم الرباعي</h2>
            <x-portal-identity-form-fields />
        </div>

        <div>
            <h2 class="mb-3 text-sm font-bold text-gray-800">بيانات الحساب</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email') }}" required dir="ltr"
                        class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25 @error('email') border-brand-danger @enderror" />
                    @error('email') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
                    <input type="password" name="password" required
                        class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" required
                        class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25" />
                </div>
            </div>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        يُنشأ الحساب تلقائياً كحساب مستفيد. تاريخ الميلاد يُستخدم كبيان شخصي فقط ولا يؤثر على أهلية البرامج.
    </p>

    <button type="submit" class="mt-5 w-full py-3 rounded-xl bg-brand text-white font-semibold text-sm hover:opacity-95 transition">
        إنشاء الحساب
    </button>

</form>

<p class="mt-6 text-center text-sm text-gray-500">
    لديك حساب بالفعل؟
    <a href="{{ route('login') }}" class="text-brand font-medium hover:underline">تسجيل الدخول</a>
</p>

@endsection
