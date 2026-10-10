<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyCompletion extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'registration_id';

    protected $fillable = [
        'program_survey_id',
        'registration_id',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(ProgramSurvey::class, 'program_survey_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ProgramRegistration::class, 'registration_id');
    }
}
