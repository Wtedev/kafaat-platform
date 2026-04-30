<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\VolunteerRegistration::class);
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'opportunity_id' => [
                'required',
                'integer',
                Rule::exists('volunteer_opportunities', 'id'),
                Rule::unique('volunteer_registrations')
                    ->where('opportunity_id', $this->integer('opportunity_id'))
                    ->where('user_id', $userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'opportunity_id.unique' => 'You have already registered for this volunteer opportunity.',
        ];
    }
}
