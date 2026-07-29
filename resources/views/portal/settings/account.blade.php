@extends('layouts.portal')
@section('title', 'بيانات الدخول')

@php
    $showOtpStep = ($pendingEmailChange ?? null) !== null
        || session('email_change_step') === 'otp'
        || $errors->has('code');
    $openEmailModal = $showOtpStep
        || session('email_change_open')
        || $errors->has('email')
        || $errors->has('email_confirmation');
@endphp

@section('content')
<x-portal.settings-shell title="بيانات الدخول" subtitle="معلومات تسجيل الدخول الأساسية.">
    @if (session('success'))
    <div class="mb-4 {{ config('brand.classes.alert_success') }}">
        {{ session('success') }}
    </div>
    @endif

    @if (session('status') && ! $openEmailModal)
    <div class="mb-4 {{ config('brand.classes.alert_success') }}">
        {{ session('status') }}
    </div>
    @endif

    <x-portal.settings-card>
        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1 text-right">
                    <p class="text-xs font-medium text-gray-500">البريد الإلكتروني</p>
                    <p class="mt-1 truncate text-sm font-semibold text-gray-900" dir="ltr">{{ $user->email }}</p>
                    <p class="mt-1.5">
                        @if ($user->email_verified_at)
                        <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">تم التحقق</span>
                        @else
                        <span class="inline-flex items-center rounded-lg bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">غير مُتحقق</span>
                        @endif
                    </p>
                </div>
                <button
                    type="button"
                    id="open-email-change-modal"
                    class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-95"
                    style="background:#335483"
                >
                    تغيير البريد الإلكتروني
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
            <dd class="text-sm text-gray-900" dir="ltr">{{ $user->phone ?: '—' }}</dd>
            <dt class="text-xs font-medium text-gray-500">رقم الجوال</dt>
        </div>

        <x-portal.settings-row href="{{ route('portal.settings.password') }}" label="تغيير كلمة المرور" hint="تحديث كلمة مرور الحساب" class="border-t border-slate-100">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>
    </x-portal.settings-card>
</x-portal.settings-shell>
@endsection

@push('styles')
<style>
    #email-change-modal {
        position: fixed;
        inset: 0;
        z-index: 100;
        margin: auto;
        width: min(calc(100% - 2rem), 26rem);
        max-height: calc(100vh - 2rem);
        overflow: auto;
        border: 1px solid #c5d4e4;
        padding: 0;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    #email-change-modal::backdrop {
        background: rgba(15, 23, 42, 0.45);
    }
</style>
@endpush

@push('modals')
<dialog id="email-change-modal" class="text-right" data-step="{{ $showOtpStep ? 'otp' : 'email' }}">
    <div class="p-5 sm:p-6" data-email-step {{ $showOtpStep ? 'hidden' : '' }}>
        <h2 class="text-base font-bold text-gray-900">تغيير البريد الإلكتروني</h2>
        <p class="mt-1.5 text-sm text-gray-600">أدخل البريد الجديد ثم أكّده. سنرسل رمز التحقق إلى البريد الجديد فقط.</p>

        @if ($errors->has('email') || $errors->has('email_confirmation'))
        <div class="mt-4 {{ config('brand.classes.alert_danger') }}">
            @foreach (['email', 'email_confirmation'] as $field)
                @error($field)<p>{{ $message }}</p>@enderror
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('portal.settings.email.change') }}" class="mt-5 space-y-3.5" id="email-change-start-form">
            @csrf
            <div>
                <label for="email-change-email" class="mb-1 block text-xs font-medium text-gray-600">البريد الإلكتروني الجديد</label>
                <input
                    type="email"
                    id="email-change-email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    dir="ltr"
                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('email') border-brand-danger @enderror"
                />
            </div>
            <div>
                <label for="email-change-confirm" class="mb-1 block text-xs font-medium text-gray-600">تأكيد البريد الإلكتروني</label>
                <input
                    type="email"
                    id="email-change-confirm"
                    name="email_confirmation"
                    value="{{ old('email_confirmation') }}"
                    required
                    autocomplete="email"
                    dir="ltr"
                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('email_confirmation') border-brand-danger @enderror"
                />
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="email-change-close rounded-xl px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">إلغاء</button>
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95" style="background:#335483" data-loading-label="جاري الإرسال...">
                    إرسال رمز التحقق
                </button>
            </div>
        </form>
    </div>

    <div class="p-5 sm:p-6" data-otp-step {{ $showOtpStep ? '' : 'hidden' }}>
        <h2 class="text-base font-bold text-gray-900">رمز التحقق</h2>
        <p class="mt-1.5 text-sm text-gray-600">
            أرسلنا رمزاً إلى
            <span class="font-medium text-gray-800" dir="ltr">{{ $pendingEmailMasked ?? 'بريدك الجديد' }}</span>.
            أدخل الرمز لإتمام التغيير.
        </p>

        @if (session('status') && $showOtpStep)
        <div class="mt-4 {{ config('brand.classes.alert_success') }}">
            {{ session('status') }}
        </div>
        @endif

        @error('code')
        <div class="mt-4 {{ config('brand.classes.alert_danger') }}">
            {{ $message }}
        </div>
        @enderror

        <form method="POST" action="{{ route('portal.settings.email.change.verify') }}" class="mt-5 space-y-3.5" id="email-change-verify-form">
            @csrf
            <div>
                <label for="email-change-code" class="mb-1 block text-xs font-medium text-gray-600">رمز التحقق</label>
                <input
                    type="text"
                    id="email-change-code"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    required
                    placeholder="000000"
                    dir="ltr"
                    class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-center text-lg font-bold tracking-[0.35em] focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('code') border-brand-danger @enderror"
                />
            </div>
            <div class="flex flex-wrap justify-end gap-2 pt-1">
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95" style="background:#335483">
                    تأكيد
                </button>
            </div>
        </form>

        <div class="mt-3 flex flex-col gap-2">
            <form method="POST" action="{{ route('portal.settings.email.change.resend') }}" id="email-change-resend-form">
                @csrf
                <button
                    type="submit"
                    id="email-change-resend-btn"
                    class="w-full rounded-xl border border-gray-200 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    data-cooldown="{{ (int) ($resendCooldownSeconds ?? 0) }}"
                >
                    إعادة إرسال الرمز
                </button>
                <p id="email-change-resend-hint" class="mt-1.5 hidden text-center text-xs text-gray-500"></p>
            </form>

            <form method="POST" action="{{ route('portal.settings.email.change.cancel') }}">
                @csrf
                <button type="submit" class="w-full rounded-xl px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                    إلغاء دون تغيير البريد
                </button>
            </form>
        </div>
    </div>
</dialog>
@endpush

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('email-change-modal');
    var openBtn = document.getElementById('open-email-change-modal');
    if (!modal || !openBtn) return;

    var shouldOpen = @json($openEmailModal);

    openBtn.addEventListener('click', function () {
        if (typeof modal.showModal === 'function') modal.showModal();
    });

    modal.querySelectorAll('.email-change-close').forEach(function (btn) {
        btn.addEventListener('click', function () { modal.close(); });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) modal.close();
    });

    if (shouldOpen && typeof modal.showModal === 'function') {
        modal.showModal();
    }

    var resendBtn = document.getElementById('email-change-resend-btn');
    var resendHint = document.getElementById('email-change-resend-hint');
    var cooldown = resendBtn ? parseInt(resendBtn.getAttribute('data-cooldown') || '0', 10) : 0;

    function tickCooldown() {
        if (!resendBtn || !resendHint) return;
        if (cooldown <= 0) {
            resendBtn.disabled = false;
            resendHint.classList.add('hidden');
            resendHint.textContent = '';
            return;
        }
        resendBtn.disabled = true;
        resendHint.classList.remove('hidden');
        resendHint.textContent = 'يمكنك إعادة الإرسال بعد ' + cooldown + ' ثانية.';
        cooldown -= 1;
        setTimeout(tickCooldown, 1000);
    }

    if (cooldown > 0) tickCooldown();

    var startForm = document.getElementById('email-change-start-form');
    if (startForm) {
        startForm.addEventListener('submit', function () {
            var submitBtn = startForm.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = submitBtn.getAttribute('data-loading-label') || 'جاري الإرسال...';
            }
        });
    }
})();
</script>
@endpush
