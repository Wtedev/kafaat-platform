@extends('layouts.auth')
@section('title', 'التحقق من البريد')
@section('container_width', 'max-w-md')
@section('content')

<x-auth.signup-steps :current="$signupStep ?? 2" />

<div class="mb-6 text-center">
    <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl" style="background:#e9eff6">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="#335483">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
    </div>
    <h1 class="text-xl font-bold text-gray-900">التحقق من البريد الإلكتروني</h1>
    <p class="mt-2 text-sm leading-relaxed text-gray-600">
        أرسلنا رمز تحقق مكوّناً من 6 أرقام إلى
        <span class="font-semibold text-gray-900" dir="ltr">{{ $maskedEmail }}</span>
    </p>
    <p class="mt-2 text-sm font-medium text-brand">لن يتم إنشاء حسابك حتى يتم التحقق من بريدك الإلكتروني.</p>
</div>

@if (session('status'))
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_success') }} px-4 py-3 text-center text-sm">
    {{ session('status') }}
</div>
@endif

@if (! empty($expired))
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-center text-sm">
    انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.
</div>
@endif

@error('code')
<div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-center text-sm">
    {{ $message }}
</div>
@enderror

<form method="POST" action="{{ route('register.verify') }}" data-signup-verify-form>
    @csrf
    <label for="code" class="sr-only">رمز التحقق</label>
    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="000000" class="mb-4 w-full rounded-xl border border-gray-200 py-3 text-center text-2xl font-bold tracking-[0.5em] text-gray-900 outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" dir="ltr">
    <button type="submit" data-signup-verify-submit class="w-full rounded-xl bg-brand py-3 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60">
        تأكيد الرمز وإنشاء الحساب
    </button>
</form>

<form method="POST" action="{{ route('register.verify.resend') }}" class="mt-3" data-signup-resend-form>
    @csrf
    <button type="submit"
        @disabled(! ($canResend ?? false))
        data-signup-resend-submit
        class="w-full rounded-xl border border-gray-200 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
        @if (($resendCooldownSeconds ?? 0) > 0)
            إعادة الإرسال بعد {{ $resendCooldownSeconds }} ثانية
        @else
            إعادة إرسال الرمز
        @endif
    </button>
</form>

<a href="{{ route('register', ['restart' => 1]) }}" class="mt-3 block w-full rounded-xl py-2.5 text-center text-sm font-medium text-gray-500 transition hover:text-gray-700">
    الرجوع وتعديل البريد الإلكتروني
</a>

<script>
(function () {
    function lockOnSubmit(formSelector, buttonSelector, loadingText) {
        var form = document.querySelector(formSelector);
        if (!form) return;
        form.addEventListener('submit', function () {
            var button = form.querySelector(buttonSelector);
            if (button && !button.disabled) {
                button.disabled = true;
                button.textContent = loadingText;
            }
        });
    }
    lockOnSubmit('[data-signup-verify-form]', '[data-signup-verify-submit]', 'جاري التحقق...');
    lockOnSubmit('[data-signup-resend-form]', '[data-signup-resend-submit]', 'جاري الإرسال...');
})();
</script>

@endsection
