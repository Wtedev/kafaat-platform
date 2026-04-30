<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStatus;
use App\Models\VolunteerOpportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', VolunteerOpportunity::class);
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            'slug'           => ['nullable', 'string', 'max:255', 'unique:volunteer_opportunities,slug', 'regex:/^[a-z0-9-]+$/'],
            'description'    => ['nullable', 'string'],
            'capacity'       => ['nullable', 'integer', 'min:1'],
            'hours_expected' => ['nullable', 'numeric', 'min:0.01', 'max:9999.99'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'         => ['nullable', Rule::enum(OpportunityStatus::class)],
        ];
    }
}
