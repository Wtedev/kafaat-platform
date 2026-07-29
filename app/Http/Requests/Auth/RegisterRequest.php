<?php

namespace App\Http\Requests\Auth;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\User;
use App\Rules\UniqueIdentityLookupHash;
use App\Rules\ValidActivePrivacyPolicyVersion;
use App\Rules\ValidIdentityNumber;
use App\Rules\ValidPersonNamePart;
use App\Rules\ValidSaudiMobile;
use App\Services\Auth\PendingRegistrationService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\SaudiPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $identityType = IdentityType::tryFrom((string) $this->input('identity_type'));
        $email = $this->input('email');
        $emailConfirmation = $this->input('email_confirmation');

        $this->merge([
            'phone' => SaudiPhoneService::normalize($this->input('phone')),
            'identity_number' => IdentityNumberService::normalize($this->input('identity_number')),
            'identity_type' => $identityType?->value,
            'email' => is_string($email) ? PendingRegistrationService::normalizeEmail($email) : $email,
            'email_confirmation' => is_string($emailConfirmation)
                ? PendingRegistrationService::normalizeEmail($emailConfirmation)
                : $emailConfirmation,
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
            'gender' => ['required', Rule::enum(ProfileGender::class)],
            'email' => ['required', 'string', 'email', 'max:255', 'confirmed'],
            'email_confirmation' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', new ValidSaudiMobile],
            'password' => ['required', 'confirmed', Password::min(8)],
            'privacy_policy_version' => ['required', 'string', new ValidActivePrivacyPolicyVersion],
            'privacy_policy_acknowledged' => ['required', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('email')) {
                return;
            }

            $email = (string) $this->input('email', '');

            if ($email === '') {
                return;
            }

            $exists = User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'email',
                    'يوجد حساب مرتبط بهذا البريد الإلكتروني، يمكنك تسجيل الدخول بدلًا من إنشاء حساب جديد.',
                );
            }
        });
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
            'gender.required' => 'الجنس مطلوب.',
            'gender.enum' => 'يرجى اختيار ذكر أو أنثى.',
            'email.required' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'email.confirmed' => 'البريد الإلكتروني وتأكيد البريد الإلكتروني غير متطابقين.',
            'email_confirmation.required' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'email_confirmation.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'privacy_policy_acknowledged.accepted' => 'يجب الإقرار بأنك اطلعت على سياسة الخصوصية.',
        ];
    }
}
