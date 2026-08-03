<?php

namespace App\Http\Requests\Portal;

use App\Support\Auth\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StartEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email'))
                ? EmailNormalizer::normalize($this->input('email'))
                : $this->input('email'),
            'email_confirmation' => is_string($this->input('email_confirmation'))
                ? EmailNormalizer::normalize($this->input('email_confirmation'))
                : $this->input('email_confirmation'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'email_confirmation' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'email_confirmation.required' => 'يرجى تأكيد البريد الإلكتروني.',
        ];
    }
}
