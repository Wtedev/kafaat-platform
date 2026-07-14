@php
$p = $user->profile;
@endphp

<form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" id="portal-profile-form" novalidate>
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
        <div class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:gap-5 sm:px-5 sm:py-5">
            <div class="flex shrink-0 justify-center sm:justify-start">
                <div class="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 ring-2 ring-white ring-offset-2 ring-offset-slate-100 sm:h-24 sm:w-24">
                    @if ($p?->avatarUrl())
                    <img id="avatar-preview-img" src="{{ $p->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                    @else
                    <span id="avatar-preview-initials" class="text-lg font-bold text-slate-600">{{ \App\Models\Profile::initialsFromName($user->fullName()) }}</span>
                    <img id="avatar-preview-img" src="" alt="" class="hidden h-full w-full object-cover" />
                    @endif
                </div>
            </div>
            <div class="min-w-0 flex-1 text-center sm:text-right">
                <p class="text-sm font-medium text-slate-800">صورتك في المنصة</p>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                    تُعرض في ملفك الشخصي وقائمة حسابك. الصيغ المدعومة: JPG، PNG، WebP — بحد أقصى 2 م.ب.
                </p>
                <div class="mt-3 flex flex-col items-center gap-2 sm:flex-row sm:items-center">
                    <label for="avatar-input" class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-[#335483] shadow-sm transition hover:border-[#335483]/20 hover:bg-slate-50">
                        {{ $p?->avatarUrl() ? 'استبدال الصورة' : 'رفع صورة' }}
                    </label>
                    <span id="avatar-file-name" class="hidden truncate text-xs text-slate-500"></span>
                </div>
                <input id="avatar-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" />
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
                :gender="old('gender', $p?->gender?->value)"
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

    <div class="hidden justify-end md:flex">
        <button type="submit" class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">
            حفظ التعديلات
        </button>
    </div>
</form>

<x-portal.mobile-form-submit-bar form="portal-profile-form" label="حفظ التعديلات" />

@push('scripts')
<script>
(function () {
    var input = document.getElementById('avatar-input');
    var fileName = document.getElementById('avatar-file-name');
    var previewImg = document.getElementById('avatar-preview-img');
    var initials = document.getElementById('avatar-preview-initials');
    if (!input) return;

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;

        if (fileName) {
            fileName.textContent = 'الملف: ' + file.name;
            fileName.classList.remove('hidden');
        }

        if (previewImg && file.type.indexOf('image/') === 0) {
            previewImg.src = URL.createObjectURL(file);
            previewImg.classList.remove('hidden');
            if (initials) initials.classList.add('hidden');
        }
    });
})();
</script>
@endpush
