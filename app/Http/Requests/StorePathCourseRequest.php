<?php

namespace App\Http\Requests;

use App\Enums\CourseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePathCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Requires update permission on the parent learning path
        return $this->user()->can('update', $this->route('learning_path'));
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:1'],
            'status'      => ['nullable', Rule::enum(CourseStatus::class)],
        ];
    }
}
