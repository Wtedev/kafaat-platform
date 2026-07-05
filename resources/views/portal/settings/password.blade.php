@extends('layouts.portal')
@section('title', 'تغيير كلمة المرور')

@section('content')
@include('portal.settings.partials.back-link', ['backRoute' => 'portal.settings.account', 'backLabel' => 'بيانات الدخول'])

<section class="mb-8 text-right">
    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">تغيير كلمة المرور</h1>
    <p class="mt-2 text-sm text-gray-600">أدخل كلمة مرورك الحالية ثم اختر كلمة مرور جديدة.</p>
</section>

<form method="POST" action="{{ route('portal.settings.password.update') }}" class="max-w-xl space-y-5">
    @csrf
    @method('PATCH')

    @if ($errors->any())
    <div class="rounded-2xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="rounded-3xl border border-slate-200/70 bg-white px-5 py-5 shadow-sm">
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">كلمة المرور الحالية</label>
                <input type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/40 @error('current_password') border-brand-danger @enderror" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">كلمة المرور الجديدة</label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/40 @error('password') border-brand-danger @enderror" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/40" />
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-2xl px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
            حفظ كلمة المرور
        </button>
    </div>
</form>
@endsection
