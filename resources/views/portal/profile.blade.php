@extends('layouts.portal')
@section('title', 'ملفي الشخصي')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">ملفي الشخصي</h1>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 max-w-2xl">
    <form method="POST" action="{{ route('portal.profile.update') }}" novalidate>
        @csrf
        @method('PATCH')

        @if ($errors->any())
        <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="grid sm:grid-cols-2 gap-5">

            {{-- Name --}}
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('name') border-red-400 @enderror" />
                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Phone --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الجوال</label>
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('phone') border-red-400 @enderror" />
                @error('phone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- City --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المدينة</label>
                <input type="text" name="city" value="{{ old('city', optional($user->profile)->city) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('city') border-red-400 @enderror" />
                @error('city') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Birth date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
                <input type="date" name="birth_date" value="{{ old('birth_date', optional(optional($user->profile)->birth_date)->format('Y-m-d')) }}" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('birth_date') border-red-400 @enderror" />
                @error('birth_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Gender --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الجنس</label>
                <select name="gender" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('gender') border-red-400 @enderror">
                    <option value="">— اختر —</option>
                    <option value="male" @selected(old('gender', optional($user->profile)->gender) === 'male')>ذكر</option>
                    <option value="female" @selected(old('gender', optional($user->profile)->gender) === 'female')>أنثى</option>
                </select>
                @error('gender') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Bio --}}
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">نبذة شخصية</label>
                <textarea name="bio" rows="4" class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 @error('bio') border-red-400 @enderror">{{ old('bio', optional($user->profile)->bio) }}</textarea>
                @error('bio') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

        </div>

        <div class="mt-6">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
                حفظ التغييرات
            </button>
        </div>
    </form>
</div>
@endsection
