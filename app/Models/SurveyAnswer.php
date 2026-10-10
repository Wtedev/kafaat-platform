<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SurveyAnswer extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'survey_response_id',
        'program_survey_question_id',
        'value',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $answer): void {
            if (! filled($answer->id)) {
                $answer->id = (string) Str::uuid();
            }
        });

        static::created(function (self $answer): void {
            if (! $answer->belongsToAnonymousSurvey()) {
                return;
            }

            DB::table('survey_answers')->where('id', $answer->id)->update([
                'created_at' => null,
                'updated_at' => null,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ProgramSurveyQuestion::class, 'program_survey_question_id');
    }

    private function belongsToAnonymousSurvey(): bool
    {
        $surveyId = SurveyResponse::query()->whereKey($this->survey_response_id)->value('program_survey_id');
        if ($surveyId === null) {
            return false;
        }

        return (bool) ProgramSurvey::query()->whereKey($surveyId)->value('is_anonymous');
    }
}
