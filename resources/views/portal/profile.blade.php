@extends('layouts.portal')
@section('title', 'ملفي الشخصي')

@php
$p = $user->profile;
@endphp

@section('content')
<h1 class="mb-2 text-2xl font-bold text-gray-900">ملفي الشخصي</h1>
<p class="mb-8 text-sm text-gray-600">حدّث صورتك وبياناتك الرسمية والمسمى المهني.</p>

<div class="max-w-3xl space-y-8">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
        <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PATCH')

            @if ($errors->any())
            <div class="mb-6 rounded-xl {{ config('brand.classes.alert_danger') }}">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <h2 class="mb-4 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">الصورة الشخصية</h2>
            <div class="mb-8 flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gray-200 text-2xl font-bold text-gray-600 ring-4 ring-[#F8FAFC] shadow-inner sm:h-32 sm:w-32">
                    @if ($p?->avatarUrl())
                    <img src="{{ $p->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                    @else
                    {{ \App\Models\Profile::initialsFromName($user->fullName()) }}
                    @endif
                </div>
                <div class="min-w-0 flex-1 text-center sm:text-right">
                    <label class="mb-2 block text-sm font-medium text-gray-700">رفع أو تغيير الصورة (اختياري)</label>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full max-w-md text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-[#e9eff6] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[#335483] sm:ms-0 sm:me-auto" />
                    @error('avatar') <p class="mt-2 text-xs text-brand-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <h2 class="mb-4 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">البيانات الرسمية</h2>
            <x-portal-identity-form-fields
                :first-name="old('first_name', $user->first_name)"
                :father-name="old('father_name', $user->father_name)"
                :grandfather-name="old('grandfather_name', $user->grandfather_name)"
                :family-name="old('family_name', $user->family_name)"
                :birth-date="old('birth_date', optional($p?->birth_date)->toDateString())"
                :phone="old('phone', $user->phone)"
                :show-identity-fields="true"
                :identity-locked="$user->hasIdentityOnRecord()"
            />

            <h2 class="mb-4 mt-8 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">بيانات إضافية</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">البريد الإلكتروني</label>
                    <input type="email" value="{{ $user->email }}" readonly class="w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600" dir="ltr" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">المدينة</label>
                    <input type="text" name="city" value="{{ old('city', $p?->city) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/40 @error('city') border-brand-danger @enderror" />
                    @error('city') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">المسمى الوظيفي</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $p?->job_title) }}" maxlength="160" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/40 @error('job_title') border-brand-danger @enderror" />
                    @error('job_title') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end border-t border-gray-100 pt-6">
                <button type="submit" class="rounded-xl px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
