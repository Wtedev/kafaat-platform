<?php

namespace App\Models;

use App\Enums\SurveyQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyTemplateQuestion extends Model
{
    protected $fillable = [
        'survey_template_id',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'survey_template_id');
    }
}
