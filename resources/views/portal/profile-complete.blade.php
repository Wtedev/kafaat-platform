@extends('layouts.portal')
@section('title', 'استكمال بيانات الحساب')

@section('content')
<h1 class="mb-2 text-2xl font-bold text-gray-900">استكمال بيانات الحساب</h1>
<p class="mb-6 text-sm text-gray-600">
    يرجى إدخال بياناتك الرسمية لإكمال ملفك. الاسم الحالي في النظام:
    <strong>{{ $user->name }}</strong> — يرجى تأكيد الاسم الرباعي يدوياً.
</p>

<div class="max-w-3xl rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
    <form method="POST" action="{{ route('portal.profile.complete.store') }}" novalidate>
        @csrf

        @if ($errors->any())
        <div class="mb-6 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <x-portal-identity-form-fields
            :first-name="old('first_name', $user->first_name)"
            :father-name="old('father_name', $user->father_name)"
            :grandfather-name="old('grandfather_name', $user->grandfather_name)"
            :family-name="old('family_name', $user->family_name)"
            :birth-date="old('birth_date', optional($user->profile?->birth_date)->toDateString())"
            :phone="old('phone', $user->phone)"
            :show-identity-fields="! $user->hasIdentityOnRecord()"
            :identity-locked="false"
        />

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-6">
            <a href="{{ route('portal.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">تخطي الآن</a>
            <button type="submit" class="rounded-xl px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                حفظ البيانات
            </button>
        </div>
    </form>
</div>
@endsection
