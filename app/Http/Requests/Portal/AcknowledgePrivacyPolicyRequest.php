<?php

namespace App\Http\Requests\Portal;

use App\Rules\ValidActivePrivacyPolicyVersion;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgePrivacyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'privacy_policy_acknowledged' => ['required', 'accepted'],
            'privacy_policy_version' => ['required', 'string', new ValidActivePrivacyPolicyVersion],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privacy_policy_acknowledged.accepted' => 'يجب الإقرار بأنك اطلعت على سياسة الخصوصية.',
        ];
    }
}
