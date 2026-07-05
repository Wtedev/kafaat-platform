@extends('layouts.portal')
@section('title', 'بيانات الدخول')

@section('content')
@include('portal.settings.partials.back-link')

<section class="mb-8 text-right">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">بيانات الدخول</h1>
    <p class="mt-2 text-sm text-gray-600">معلومات تسجيل الدخول إلى حسابك في المنصة.</p>
</section>

<div class="max-w-2xl space-y-4">
    <div class="rounded-3xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Account</p>
        <dl class="mt-4 space-y-4">
            <div>
                <dt class="text-sm font-medium text-gray-700">البريد الإلكتروني</dt>
                <dd class="mt-1 text-sm text-gray-900" dir="ltr">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-700">رقم الجوال</dt>
                <dd class="mt-1 text-sm text-gray-900" dir="ltr">{{ $user->phone ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <a href="{{ route('portal.settings.password') }}" class="flex items-center justify-between gap-4 rounded-3xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm transition hover:bg-slate-50">
        <svg class="h-4 w-4 shrink-0 rotate-180 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <div class="min-w-0 flex-1 text-right">
            <p class="text-sm font-semibold text-gray-900">تغيير كلمة المرور</p>
            <p class="mt-0.5 text-xs text-gray-500">تحديث كلمة مرور حسابك</p>
        </div>
    </a>
</div>
@endsection
