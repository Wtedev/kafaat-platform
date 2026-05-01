<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('volunteer_opportunity'));
    }

    public function rules(): array
    {
        $opportunityId = $this->route('volunteer_opportunity')?->id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('volunteer_opportunities', 'slug')->ignore($opportunityId), 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'hours_expected' => ['nullable', 'numeric', 'min:0.01', 'max:9999.99'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::enum(OpportunityStatus::class)],
        ];
    }
}
