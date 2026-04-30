<?php

namespace App\Http\Requests;

use App\Models\ProgramRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProgramRegistration::class);
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'training_program_id' => [
                'required',
                'integer',
                Rule::exists('training_programs', 'id'),
                // Prevent duplicate registrations at the validation layer
                Rule::unique('program_registrations')
                    ->where('training_program_id', $this->integer('training_program_id'))
                    ->where('user_id', $userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'training_program_id.unique' => 'You have already registered for this training program.',
        ];
    }
}
