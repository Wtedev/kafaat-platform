@extends('layouts.portal')
@section('title', 'تغيير كلمة المرور')

@section('content')
<x-portal.settings-shell title="تغيير كلمة المرور" subtitle="أدخل كلمة المرور الحالية ثم الجديدة." back-route="portal.settings.account" back-label="بيانات الدخول">
    <form method="POST" action="{{ route('portal.settings.password.update') }}" id="portal-password-form" class="space-y-4">
        @csrf
        @method('PATCH')

        @if ($errors->any())
        <div class="rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <x-portal.settings-card class="px-4 py-4 sm:px-5 sm:py-5">
            <div class="space-y-3.5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('current_password') border-brand-danger @enderror" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">كلمة المرور الجديدة</label>
                    <input type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('password') border-brand-danger @enderror" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30" />
                </div>
            </div>
        </x-portal.settings-card>

        <div class="hidden justify-end md:flex">
            <button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                حفظ التعديلات
            </button>
        </div>
    </form>

    <x-portal.mobile-form-submit-bar form="portal-password-form" label="حفظ التعديلات" />
</x-portal.settings-shell>
@endsection
