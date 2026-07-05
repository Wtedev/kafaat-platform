@php
$p = $user->profile;
@endphp

<form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PATCH')

    @if ($errors->any())
    <div class="mb-4 rounded-xl {{ config('brand.classes.alert_danger') }} px-4 py-3 text-sm">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <x-portal.settings-card class="mb-4">
        <p class="border-b border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">الصورة الشخصية</p>
        <div class="flex flex-col items-center gap-4 px-4 py-4 sm:flex-row sm:items-start sm:px-5 sm:py-5">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 text-lg font-bold text-slate-600 sm:h-24 sm:w-24">
                @if ($p?->avatarUrl())
                <img src="{{ $p->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                @else
                {{ \App\Models\Profile::initialsFromName($user->fullName()) }}
                @endif
            </div>
            <div class="min-w-0 flex-1 text-center sm:text-right">
                <label class="mb-2 block text-xs font-medium text-gray-600">رفع أو تغيير الصورة</label>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-[#e9eff6] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#335483]" />
                @error('avatar') <p class="mt-2 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-portal.settings-card>

    <x-portal.settings-card class="mb-4">
        <p class="border-b border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">البيانات الرسمية</p>
        <div class="px-4 py-4 sm:px-5 sm:py-5">
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
        </div>
    </x-portal.settings-card>

    <x-portal.settings-card class="mb-4">
        <p class="border-b border-slate-100 px-4 py-2.5 text-[11px] font-semibold text-slate-400 sm:px-5">بيانات إضافية</p>
        <div class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5 sm:py-5">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600">البريد الإلكتروني</label>
                <input type="email" value="{{ $user->email }}" readonly class="w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600" dir="ltr" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">المدينة</label>
                <input type="text" name="city" value="{{ old('city', $p?->city) }}" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('city') border-brand-danger @enderror" />
                @error('city') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">المسمى الوظيفي</label>
                <input type="text" name="job_title" value="{{ old('job_title', $p?->job_title) }}" maxlength="160" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#335483]/30 @error('job_title') border-brand-danger @enderror" />
                @error('job_title') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-portal.settings-card>

    <div class="flex justify-end">
        <button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
            حفظ التغييرات
        </button>
    </div>
</form>
