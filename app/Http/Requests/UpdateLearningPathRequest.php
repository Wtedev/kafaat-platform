<?php

namespace App\Http\Requests;

use App\Enums\PathStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearningPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('learning_path'));
    }

    public function rules(): array
    {
        $pathId = $this->route('learning_path')?->id;

        return [
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'slug'        => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('learning_paths', 'slug')->ignore($pathId), 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'capacity'    => ['nullable', 'integer', 'min:1'],
            'status'      => ['nullable', Rule::enum(PathStatus::class)],
        ];
    }
}
