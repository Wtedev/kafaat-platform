@props([
    'firstName' => '',
    'fatherName' => '',
    'grandfatherName' => '',
    'familyName' => '',
    'birthDate' => '',
    'phone' => '',
    'showIdentityFields' => true,
    'identityLocked' => false,
    'layout' => 'default',
    'section' => null,
])

@php
use App\Enums\IdentityType;

$inputClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/25';
$labelClass = 'mb-1.5 block text-sm font-medium text-gray-700';
$sectioned = $layout === 'sectioned';
$showNames = ! $sectioned || $section === 'names';
$showIdentityContact = ! $sectioned || $section === 'identity-contact';
@endphp

@if ($sectioned)
    @if ($showNames)
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="{{ $labelClass }}">الاسم الأول <span class="text-brand-danger">*</span></label>
            <input type="text" name="first_name" value="{{ old('first_name', $firstName ?? '') }}" required maxlength="100"
                class="{{ $inputClass }} @error('first_name') border-brand-danger @enderror" />
            @error('first_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $labelClass }}">اسم الأب <span class="text-brand-danger">*</span></label>
            <input type="text" name="father_name" value="{{ old('father_name', $fatherName ?? '') }}" required maxlength="100"
                class="{{ $inputClass }} @error('father_name') border-brand-danger @enderror" />
            @error('father_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $labelClass }}">اسم الجد <span class="text-brand-danger">*</span></label>
            <input type="text" name="grandfather_name" value="{{ old('grandfather_name', $grandfatherName ?? '') }}" required maxlength="100"
                class="{{ $inputClass }} @error('grandfather_name') border-brand-danger @enderror" />
            @error('grandfather_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $labelClass }}">اسم العائلة <span class="text-brand-danger">*</span></label>
            <input type="text" name="family_name" value="{{ old('family_name', $familyName ?? '') }}" required maxlength="100"
                class="{{ $inputClass }} @error('family_name') border-brand-danger @enderror" />
            @error('family_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
        </div>
    </div>
    @endif

    @if ($showIdentityContact)
    <div class="grid gap-4 sm:grid-cols-2">
        @if ($showIdentityFields)
            @if ($identityLocked)
                <div class="sm:col-span-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    <p class="font-medium text-gray-800">رقم الهوية / الإقامة</p>
                    <p class="mt-1">{{ auth()->user()?->maskedIdentityNumber() ?? '—' }}</p>
                    <p class="mt-2 text-xs text-gray-500">لتعديل رقم الهوية بعد تسجيله، يرجى التواصل مع الدعم.</p>
                </div>
            @else
                <div>
                    <label class="{{ $labelClass }}">نوع الهوية <span class="text-brand-danger">*</span></label>
                    <select name="identity_type" required class="{{ $inputClass }} @error('identity_type') border-brand-danger @enderror">
                        <option value="">اختر النوع</option>
                        @foreach (IdentityType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('identity_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('identity_type') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">رقم الهوية / الإقامة <span class="text-brand-danger">*</span></label>
                    <input type="text" name="identity_number" value="{{ old('identity_number') }}" required inputmode="numeric" autocomplete="off"
                        class="{{ $inputClass }} @error('identity_number') border-brand-danger @enderror" dir="ltr" />
                    @error('identity_number') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
                </div>
            @endif
        @endif

        <div>
            <label class="{{ $labelClass }}">تاريخ الميلاد <span class="text-brand-danger">*</span></label>
            <input type="date" name="birth_date" value="{{ old('birth_date', $birthDate ?? '') }}" required max="{{ now()->toDateString() }}"
                class="{{ $inputClass }} @error('birth_date') border-brand-danger @enderror" />
            @error('birth_date') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $labelClass }}">رقم الجوال <span class="text-brand-danger">*</span></label>
            <input type="tel" name="phone" value="{{ old('phone', $phone ?? '') }}" required placeholder="05XXXXXXXX" autocomplete="tel"
                class="{{ $inputClass }} @error('phone') border-brand-danger @enderror" dir="ltr" />
            @error('phone') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
        </div>
    </div>
    @endif
@else
<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="{{ $labelClass }}">الاسم الأول <span class="text-brand-danger">*</span></label>
        <input type="text" name="first_name" value="{{ old('first_name', $firstName ?? '') }}" required maxlength="100"
            class="{{ $inputClass }} @error('first_name') border-brand-danger @enderror" />
        @error('first_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">اسم الأب <span class="text-brand-danger">*</span></label>
        <input type="text" name="father_name" value="{{ old('father_name', $fatherName ?? '') }}" required maxlength="100"
            class="{{ $inputClass }} @error('father_name') border-brand-danger @enderror" />
        @error('father_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">اسم الجد <span class="text-brand-danger">*</span></label>
        <input type="text" name="grandfather_name" value="{{ old('grandfather_name', $grandfatherName ?? '') }}" required maxlength="100"
            class="{{ $inputClass }} @error('grandfather_name') border-brand-danger @enderror" />
        @error('grandfather_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">اسم العائلة <span class="text-brand-danger">*</span></label>
        <input type="text" name="family_name" value="{{ old('family_name', $familyName ?? '') }}" required maxlength="100"
            class="{{ $inputClass }} @error('family_name') border-brand-danger @enderror" />
        @error('family_name') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </div>

    @if ($showIdentityFields)
        @if ($identityLocked)
            <div class="sm:col-span-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <p class="font-medium text-gray-800">رقم الهوية / الإقامة</p>
                <p class="mt-1">{{ auth()->user()?->maskedIdentityNumber() ?? '—' }}</p>
                <p class="mt-2 text-xs text-gray-500">لتعديل رقم الهوية بعد تسجيله، يرجى التواصل مع الدعم.</p>
            </div>
        @else
            <div>
                <label class="{{ $labelClass }}">نوع الهوية <span class="text-brand-danger">*</span></label>
                <select name="identity_type" required class="{{ $inputClass }} @error('identity_type') border-brand-danger @enderror">
                    <option value="">اختر النوع</option>
                    @foreach (IdentityType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('identity_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('identity_type') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $labelClass }}">رقم الهوية / الإقامة <span class="text-brand-danger">*</span></label>
                <input type="text" name="identity_number" value="{{ old('identity_number') }}" required inputmode="numeric" autocomplete="off"
                    class="{{ $inputClass }} @error('identity_number') border-brand-danger @enderror" dir="ltr" />
                @error('identity_number') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
            </div>
        @endif
    @endif

    <div>
        <label class="{{ $labelClass }}">تاريخ الميلاد <span class="text-brand-danger">*</span></label>
        <input type="date" name="birth_date" value="{{ old('birth_date', $birthDate ?? '') }}" required max="{{ now()->toDateString() }}"
            class="{{ $inputClass }} @error('birth_date') border-brand-danger @enderror" />
        @error('birth_date') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">رقم الجوال <span class="text-brand-danger">*</span></label>
        <input type="tel" name="phone" value="{{ old('phone', $phone ?? '') }}" required placeholder="05XXXXXXXX" autocomplete="tel"
            class="{{ $inputClass }} @error('phone') border-brand-danger @enderror" dir="ltr" />
        @error('phone') <p class="mt-1 text-xs text-brand-danger">{{ $message }}</p> @enderror
    </div>
</div>
@endif
