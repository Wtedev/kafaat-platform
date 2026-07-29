@extends('layouts.auth')
@section('title', 'إنشاء حساب')
@section('container_width', 'max-w-2xl')
@section('content')

<x-auth.signup-steps :current="$signupStep ?? 1" />

<div class="mb-6 text-center">
    <h1 class="text-2xl font-bold text-gray-900">إنشاء حساب جديد</h1>
    <p class="mt-2 text-sm text-gray-500">أدخل بياناتك الرسمية لإنشاء حساب مستفيد في منصة كفاءات.</p>
    <p class="mt-2 text-sm font-medium text-brand">لن يتم إنشاء حسابك حتى يتم التحقق من بريدك الإلكتروني.</p>
</div>

<form method="POST" action="{{ route('register') }}" novalidate class="space-y-5" data-signup-form>
    @csrf

    @if ($errors->any())
    <div class="rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
        <p class="mb-2 font-semibold">يرجى تصحيح الأخطاء التالية:</p>
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        @if ($errors->has('email') && str_contains($errors->first('email'), 'تسجيل الدخول'))
            <p class="mt-3">
                <a href="{{ route('login') }}" class="font-medium text-brand hover:underline">الانتقال إلى تسجيل الدخول</a>
            </p>
        @endif
    </div>
    @endif

    <section class="rounded-2xl border border-gray-100 bg-[#F8FAFC]/80 p-5 sm:p-6">
        <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand/10 text-sm font-bold text-brand">1</span>
            <div>
                <h2 class="text-sm font-bold text-gray-900">الاسم الرباعي</h2>
                <p class="text-xs text-gray-500">كما هو في الهوية الرسمية</p>
            </div>
        </div>
        <x-portal-identity-form-fields layout="sectioned" section="names" />
    </section>

    <section class="rounded-2xl border border-gray-100 bg-[#F8FAFC]/80 p-5 sm:p-6">
        <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand/10 text-sm font-bold text-brand">2</span>
            <div>
                <h2 class="text-sm font-bold text-gray-900">الهوية والتواصل</h2>
                <p class="text-xs text-gray-500">لتمييز حسابك والتواصل معك</p>
            </div>
        </div>
        <div class="mb-4 rounded-xl border border-gray-100 bg-white/80 px-4 py-3">
            <p class="text-sm font-semibold text-gray-800">لماذا نحتاج هذه المعلومات؟</p>
            <p class="mt-1 text-xs leading-relaxed text-gray-500">نستخدم نوع ورقم الهوية والجوال لتسجيل حسابك بدقة والتواصل معك بشأن البرامج والخدمات في المنصة.</p>
        </div>
        <x-portal-identity-form-fields layout="sectioned" section="identity-contact" />
    </section>

    <section class="rounded-2xl border border-gray-100 bg-[#F8FAFC]/80 p-5 sm:p-6">
        <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand/10 text-sm font-bold text-brand">3</span>
            <div>
                <h2 class="text-sm font-bold text-gray-900">بيانات الحساب</h2>
                <p class="text-xs text-gray-500">البريد الإلكتروني وكلمة المرور</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">البريد الإلكتروني <span class="text-brand-danger">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required dir="ltr" autocomplete="email"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25 @error('email') border-brand-danger @enderror" />
                @error('email') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">تأكيد البريد الإلكتروني <span class="text-brand-danger">*</span></label>
                <input type="email" name="email_confirmation" value="{{ old('email_confirmation') }}" required dir="ltr" autocomplete="email"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25 @error('email_confirmation') border-brand-danger @enderror" />
                @error('email_confirmation') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">كلمة المرور <span class="text-brand-danger">*</span></label>
                <input type="password" name="password" required autocomplete="new-password"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25 @error('password') border-brand-danger @enderror" />
                @error('password') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">تأكيد كلمة المرور <span class="text-brand-danger">*</span></label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25" />
            </div>
        </div>
    </section>

    @isset($privacyPolicy)
    <section class="rounded-2xl border border-gray-100 bg-white p-5">
        <input type="hidden" name="privacy_policy_version" value="{{ $privacyPolicy->version }}" />
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" name="privacy_policy_acknowledged" value="1" required
                class="mt-1 rounded border-gray-300 text-brand focus:ring-brand/25" />
            <span class="text-sm leading-relaxed text-gray-700">
                {{ $acknowledgementText }}
                <a href="{{ route('public.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-medium text-brand hover:underline">(الإصدار {{ $privacyPolicy->version }})</a>
            </span>
        </label>
        @error('privacy_policy_acknowledged') <p class="mt-2 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </section>
    @endisset

    <button type="submit" data-signup-submit class="w-full rounded-xl bg-brand py-3.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60">
        متابعة للتحقق من البريد
    </button>
</form>

<p class="mt-6 text-center text-sm text-gray-500">
    لديك حساب بالفعل؟
    <a href="{{ route('login') }}" class="font-medium text-brand hover:underline">تسجيل الدخول</a>
</p>

<script>
(function () {
    var form = document.querySelector('[data-signup-form]');
    if (!form) return;
    form.addEventListener('submit', function () {
        var button = form.querySelector('[data-signup-submit]');
        if (button) {
            button.disabled = true;
            button.textContent = 'جاري الإرسال...';
        }
    });
})();
</script>

@endsection
