<?php

namespace App\Http\Requests\Auth;

use App\Enums\IdentityType;
use App\Rules\UniqueIdentityLookupHash;
use App\Rules\ValidIdentityNumber;
use App\Rules\ValidPersonNamePart;
use App\Rules\ValidSaudiMobile;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\SaudiPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $identityType = IdentityType::tryFrom((string) $this->input('identity_type'));

        $this->merge([
            'phone' => SaudiPhoneService::normalize($this->input('phone')),
            'identity_number' => IdentityNumberService::normalize($this->input('identity_number')),
            'identity_type' => $identityType?->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $identityType = IdentityType::tryFrom((string) $this->input('identity_type'));

        return [
            'first_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'father_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'grandfather_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'family_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'identity_type' => ['required', Rule::enum(IdentityType::class)],
            'identity_number' => [
                'required',
                'string',
                new ValidIdentityNumber($identityType),
                new UniqueIdentityLookupHash($identityType),
            ],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(120)->toDateString()],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', new ValidSaudiMobile],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'grandfather_name.required' => 'اسم الجد مطلوب.',
            'family_name.required' => 'اسم العائلة مطلوب.',
            'identity_type.required' => 'نوع الهوية مطلوب.',
            'birth_date.required' => 'تاريخ الميلاد مطلوب.',
            'birth_date.before_or_equal' => 'تاريخ الميلاد لا يمكن أن يكون في المستقبل.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
