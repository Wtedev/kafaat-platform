<?php

namespace App\Models;

use App\Enums\SurveyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProgramSurvey extends Model
{
    protected $fillable = [
        'training_program_id',
        'type',
        'public_token',
        'opens_at',
        'closes_at',
        'is_anonymous',
        'source_template_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => SurveyType::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_anonymous' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $survey): void {
            if (! filled($survey->public_token)) {
                $survey->public_token = Str::random(40);
            }
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(SurveyTemplate::class, 'source_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProgramSurveyQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(SurveyCompletion::class);
    }

    public function accessAttempts(): HasMany
    {
        return $this->hasMany(SurveyAccessAttempt::class);
    }

    public function isOpen(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->opens_at === null || $this->closes_at === null) {
            return false;
        }

        return $at->betweenIncluded($this->opens_at, $this->closes_at);
    }

    public function statusLabel(): string
    {
        return $this->isOpen() ? 'مفتوح' : 'مغلق';
    }

    public function publicUrl(): string
    {
        return route('public.surveys.show', $this->public_token);
    }
}
