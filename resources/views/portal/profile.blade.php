@extends('layouts.portal')
@section('title', 'ملفي الشخصي')

@php
$p = $user->profile;
@endphp

@section('content')
<h1 class="mb-2 text-2xl font-bold text-gray-900">ملفي الشخصي</h1>
<p class="mb-8 text-sm text-gray-600">حدّث صورتك وبياناتك والمسمى المهني.</p>

<div class="max-w-3xl space-y-8">
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
        <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PATCH')

            @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
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
                    {{ \App\Models\Profile::initialsFromName($user->name) }}
                    @endif
                </div>
                <div class="min-w-0 flex-1 text-center sm:text-right">
                    <label class="mb-2 block text-sm font-medium text-gray-700">رفع أو تغيير الصورة (اختياري)</label>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full max-w-md text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-[#EAF2FA] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[#253B5B] sm:ms-0 sm:me-auto" />
                    @error('avatar') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
                    <p class="mt-2 text-xs text-gray-500">صورة مربعة بحد أقصى 2 ميجابايت (JPEG أو PNG أو WebP أو GIF).</p>
                </div>
            </div>

            <h2 class="mb-4 border-b border-gray-100 pb-2 text-base font-bold text-gray-900">البيانات الأساسية</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">الاسم الكامل <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('name') border-red-400 @enderror" />
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">البريد الإلكتروني</label>
                    <input type="email" value="{{ $user->email }}" readonly class="w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600" dir="ltr" />
                    <p class="mt-1 text-xs text-gray-500">غير قابل للتعديل من هذه الصفحة.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">رقم الجوال</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('phone') border-red-400 @enderror" />
                    @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">المدينة</label>
                    <input type="text" name="city" value="{{ old('city', $p?->city) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('city') border-red-400 @enderror" />
                    @error('city') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">المسمى المهني</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $p?->job_title) }}" maxlength="160" placeholder="مثال: محلل بيانات، منسّق برامج" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#253B5B]/40 @error('job_title') border-red-400 @enderror" />
                    @error('job_title') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-8 flex justify-end border-t border-gray-100 pt-6">
                <button type="submit" class="rounded-xl px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
