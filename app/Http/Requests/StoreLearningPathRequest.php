<?php

namespace App\Http\Requests;

use App\Enums\PathStatus;
use App\Models\LearningPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LearningPath::class);
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255', 'unique:learning_paths,slug', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'status'      => ['nullable', Rule::enum(PathStatus::class)],
        ];
    }
}
