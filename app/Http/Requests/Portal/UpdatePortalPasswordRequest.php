<?php

namespace App\Http\Requests\Portal;

use App\Services\Auth\AccountPasswordChangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class UpdatePortalPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => AccountPasswordChangeService::newPasswordRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();

            if ($user === null || ! Hash::check((string) $this->input('current_password'), (string) $user->password)) {
                $validator->errors()->add('current_password', AccountPasswordChangeService::MSG_CURRENT_WRONG);
            }
        });
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
