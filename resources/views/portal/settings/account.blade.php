@extends('layouts.portal')
@section('title', 'بيانات الدخول')

@section('content')
<x-portal.settings-shell title="بيانات الدخول" subtitle="معلومات تسجيل الدخول الأساسية.">
    <x-portal.settings-card>
        <dl class="divide-y divide-slate-100">
            <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                <dd class="text-sm text-gray-900" dir="ltr">{{ $user->email }}</dd>
                <dt class="text-xs font-medium text-gray-500">البريد الإلكتروني</dt>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
                <dd class="text-sm text-gray-900" dir="ltr">{{ $user->phone ?: '—' }}</dd>
                <dt class="text-xs font-medium text-gray-500">رقم الجوال</dt>
            </div>
        </dl>
        <x-portal.settings-row href="{{ route('portal.settings.password') }}" label="تغيير كلمة المرور" hint="تحديث كلمة مرور الحساب" class="border-t border-slate-100">
            <x-slot:icon>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </x-slot:icon>
        </x-portal.settings-row>
    </x-portal.settings-card>
</x-portal.settings-shell>
@endsection
