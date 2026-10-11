<?php

namespace App\Models;

use App\Services\Attendance\ProgramAttendanceLinkService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProgramAttendanceLink extends Model
{
    protected $fillable = [
        'training_program_id',
        'name',
        'open_minutes',
        'token',
        'cancelled_at',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'open_minutes' => 'integer',
            'cancelled_at' => 'datetime',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            if (! filled($link->token)) {
                $link->token = Str::random(40);
            }

            $link->open_minutes = ProgramAttendanceLinkService::OPEN_MINUTES;
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ProgramAttendanceMark::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isOpen(?Carbon $at = null): bool
    {
        if ($this->isCancelled() || $this->opens_at === null || $this->closes_at === null) {
            return false;
        }

        $at ??= now();

        return $at->greaterThanOrEqualTo($this->opens_at) && $at->lessThan($this->closes_at);
    }

    public function remainingSeconds(?Carbon $at = null): int
    {
        if (! $this->isOpen($at) || $this->closes_at === null) {
            return 0;
        }

        $at ??= now();

        return max(0, $this->closes_at->getTimestamp() - $at->getTimestamp());
    }

    public function remainingLabel(?Carbon $at = null): string
    {
        $seconds = $this->remainingSeconds($at);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function openMinutes(): int
    {
        return ProgramAttendanceLinkService::OPEN_MINUTES;
    }

    public function closesAtLabel(): ?string
    {
        if (! $this->isOpen() || $this->closes_at === null) {
            return null;
        }

        $at = $this->closes_at->timezone('Asia/Riyadh');
        $clock = strtr($at->format('g:i'), [
            '0' => '٠',
            '1' => '١',
            '2' => '٢',
            '3' => '٣',
            '4' => '٤',
            '5' => '٥',
            '6' => '٦',
            '7' => '٧',
            '8' => '٨',
            '9' => '٩',
        ]);
        $suffix = ((int) $at->format('G')) < 12 ? 'ص' : 'م';

        return 'يُغلق الساعة '.$clock.' '.$suffix;
    }

    public function publicUrl(): string
    {
        return route('public.attendance.show', $this->token);
    }

    public function trainerUrl(): string
    {
        return route('public.attendance.desk', $this->token);
    }
}
