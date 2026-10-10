<?php

namespace App\Models;

use App\Enums\SurveyQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramSurveyQuestion extends Model
{
    protected $fillable = [
        'program_survey_id',
        'position',
        'type',
        'prompt',
        'scale_min_label',
        'scale_max_label',
        'options',
        'required',
    ];

    protected function casts(): array
    {
        return [
            'type' => SurveyQuestionType::class,
            'options' => 'array',
            'required' => 'boolean',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(ProgramSurvey::class, 'program_survey_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
