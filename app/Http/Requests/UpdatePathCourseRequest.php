<?php

namespace App\Http\Requests;

use App\Enums\CourseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePathCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Requires update permission on the parent learning path
        return $this->user()->can('update', $this->route('path_course')->learningPath);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(CourseStatus::class)],
        ];
    }
}
