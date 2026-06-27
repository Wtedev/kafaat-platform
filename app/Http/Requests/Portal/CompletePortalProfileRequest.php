<?php

namespace App\Http\Requests\Portal;

use App\Enums\IdentityType;
use App\Rules\UniqueIdentityLookupHash;
use App\Rules\ValidIdentityNumber;
use App\Rules\ValidPersonNamePart;
use App\Rules\ValidSaudiMobile;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\SaudiPhoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompletePortalProfileRequest extends FormRequest
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
        $needsIdentity = $user !== null && ! filled($user->identity_number_lookup_hash);

        $identityRules = $needsIdentity
            ? ['required', 'string', new ValidIdentityNumber($identityType), new UniqueIdentityLookupHash($identityType, $user?->id)]
            : ['prohibited'];

        $identityTypeRules = $needsIdentity
            ? ['required', Rule::enum(IdentityType::class)]
            : ['prohibited'];

        return [
            'first_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'father_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'grandfather_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'family_name' => ['required', 'string', 'max:100', new ValidPersonNamePart],
            'identity_type' => $identityTypeRules,
            'identity_number' => $identityRules,
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:'.now()->subYears(120)->toDateString()],
            'phone' => ['required', 'string', new ValidSaudiMobile],
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
            'identity_number.required' => 'رقم الهوية أو الإقامة مطلوب.',
            'birth_date.required' => 'تاريخ الميلاد مطلوب.',
            'birth_date.before_or_equal' => 'تاريخ الميلاد لا يمكن أن يكون في المستقبل.',
        ];
    }
}
