<?php

namespace App\Http\Requests\Certificates;

use App\Models\TrainingProgram;
use Illuminate\Foundation\Http\FormRequest;

class SaveCertificateDesignRequest extends FormRequest
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
            'hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'elements' => ['present', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.numeric' => 'عدد الساعات يجب أن يكون رقماً.',
            'hours.min' => 'عدد الساعات لا يمكن أن يكون سالباً.',
            'hours.max' => 'عدد الساعات أكبر من الحد المسموح.',
            'elements.present' => 'قائمة الحقول مطلوبة.',
            'elements.array' => 'قائمة الحقول غير صالحة.',
        ];
    }
}
