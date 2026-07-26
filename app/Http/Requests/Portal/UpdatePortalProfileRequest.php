<?php

namespace App\Http\Requests\Portal;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Rules\UniqueIdentityLookupHash;
use App\Rules\ValidIdentityNumber;
use App\Rules\ValidPersonNamePart;
use App\Rules\ValidSaudiMobile;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\SaudiPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $identityType = IdentityType::tryFrom((string) $this->input('identity_type'));

        $merge = [
            'phone' => SaudiPhoneService::normalize($this->input('phone')),
        ];

        if ($this->filled('identity_number')) {
            $merge['identity_number'] = IdentityNumberService::normalize($this->input('identity_number'));
            $merge['identity_type'] = $identityType?->value;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $identityType = IdentityType::tryFrom((string) $this->input('identity_type'));
        $canAddIdentity = $user !== null && ! filled($user->identity_number_lookup_hash);

        $identityNumberRules = ['nullable', 'string'];
        $identityTypeRules = ['nullable', Rule::enum(IdentityType::class)];

        if ($canAddIdentity && $this->filled('identity_number')) {
            $identityNumberRules = [
                'required',
                'string',
                new ValidIdentityNumber($identityType),
                new UniqueIdentityLookupHash($identityType, $user?->id),
            ];
            $identityTypeRules = ['required', Rule::enum(IdentityType::class)];
        } elseif (! $canAddIdentity) {
            $identityNumberRules = ['prohibited'];
            $identityTypeRules = ['prohibited'];
        }

        return [
            'first_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'father_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'grandfather_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'family_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'identity_type' => $identityTypeRules,
            'identity_number' => $identityNumberRules,
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(120)->toDateString()],
            'gender' => ['required', Rule::enum(ProfileGender::class)],
            'phone' => ['required', 'string', new ValidSaudiMobile],
            'city' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                'dimensions:max_width=4000,max_height=4000',
            ],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.image' => 'يجب أن يكون الملف صورة حقيقية (JPEG أو PNG أو WebP).',
            'avatar.mimes' => 'الصيغ المسموحة فقط: JPEG و PNG و WebP. لا يُسمح بـ SVG أو GIF.',
            'avatar.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
            'avatar.dimensions' => 'أبعاد الصورة كبيرة جداً. الحد الأقصى 4000×4000 بكسل.',
        ];
    }
}
