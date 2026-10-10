<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SurveyResponse extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'program_survey_id',
        'registration_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            if (! filled($response->id)) {
                $response->id = (string) Str::uuid();
            }
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(ProgramSurvey::class, 'program_survey_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ProgramRegistration::class, 'registration_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
