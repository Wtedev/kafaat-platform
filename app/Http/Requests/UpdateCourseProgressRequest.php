<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'progress_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'progress_percentage.required' => 'نسبة التقدم مطلوبة.',
            'progress_percentage.numeric' => 'نسبة التقدم يجب أن تكون رقماً.',
            'progress_percentage.min' => 'نسبة التقدم لا تقل عن 0.',
            'progress_percentage.max' => 'نسبة التقدم لا تزيد عن 100.',
            'score.numeric' => 'الدرجة يجب أن تكون رقماً.',
            'score.min' => 'الدرجة لا تقل عن 0.',
            'score.max' => 'الدرجة لا تزيد عن 100.',
        ];
    }
}
