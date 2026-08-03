<?php

namespace App\Models;

use App\Enums\ProgramPrepDayType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProgramPrepDay extends Model
{
    protected $fillable = [
        'training_program_id',
        'prep_date',
        'delivery_type',
        'requires_attendance',
    ];

    protected function casts(): array
    {
        return [
            'prep_date' => 'date',
            'delivery_type' => ProgramPrepDayType::class,
            'requires_attendance' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $day): void {
            if ($day->requires_attendance !== null) {
                return;
            }

            $day->requires_attendance = $day->delivery_type === ProgramPrepDayType::InPerson;
        });

        static::saving(function (self $day): void {
            if ($day->delivery_type === ProgramPrepDayType::InPerson && $day->requires_attendance === null) {
                $day->requires_attendance = true;
            }
        });
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function scopeRequiresAttendance(Builder $query): Builder
    {
        return $query->where('requires_attendance', true);
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('prep_date');
    }

    public function dateString(): string
    {
        return $this->prep_date instanceof Carbon
            ? $this->prep_date->toDateString()
            : (string) $this->prep_date;
    }

    /**
     * Arabic label like «الأحد 3 أغسطس».
     */
    public function displayLabel(): string
    {
        $date = $this->prep_date instanceof Carbon
            ? $this->prep_date->copy()->timezone(config('app.timezone'))
            : Carbon::parse((string) $this->prep_date, config('app.timezone'));

        $dayNames = [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        $dayName = $dayNames[$date->dayOfWeek] ?? '';

        return trim($dayName.' '.ar_date($date, 'd MMMM'));
    }
}
