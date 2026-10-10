<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyAccessAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'program_survey_id',
        'national_id_hash',
        'ip',
        'matched',
    ];

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(ProgramSurvey::class, 'program_survey_id');
    }
}
