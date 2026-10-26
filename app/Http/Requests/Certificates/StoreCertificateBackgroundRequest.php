<?php

namespace App\Http\Requests\Certificates;

use App\Models\TrainingProgram;
use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateBackgroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof TrainingProgram
            && ($this->user()?->can('designCertificate', $program) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'background' => ['required', 'file', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'background.required' => 'اختر صورة للخلفية.',
            'background.file' => 'الملف يجب أن يكون صورة PNG أو JPG.',
            'background.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميغابايت.',
        ];
    }
}
