<?php

namespace App\Http\Requests;

use App\Models\VolunteerHour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', VolunteerHour::class);
    }

    public function rules(): array
    {
        return [
            'opportunity_id' => ['nullable', 'integer', Rule::exists('volunteer_opportunities', 'id')],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
