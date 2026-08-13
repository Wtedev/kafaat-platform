<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Services\ProgramAttendanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProgramAttendance extends Model
{
    protected $table = 'program_attendance';

    protected $fillable = [
        'program_registration_id',
        'training_date',
        'status',
        'notes',
        'internal_notes',
    ];

    public function hasInternalNote(): bool
    {
        return filled(trim((string) $this->internal_notes));
    }

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    // ─── Auto-sync attendance_percentage ─────────────────────────────────────

    /**
     * After any attendance record is saved or deleted, recalculate and persist
     * the attendance_percentage on the parent ProgramRegistration.
     *
     * Uses a direct DB update to avoid triggering Eloquent model events on
     * ProgramRegistration, which would risk infinite loops.
     *
     * Null means «—» (no due prep days yet); never coerce to 0.
     */
    protected static function booted(): void
    {
        $recalculate = static function (self $record): void {
            $registration = ProgramRegistration::with('trainingProgram')->find($record->program_registration_id);

            if ($registration === null) {
                return;
            }

            $percentage = app(ProgramAttendanceService::class)->calculatePercentage($registration);

            DB::table('program_registrations')
                ->where('id', $registration->id)
                ->update([
                    'attendance_percentage' => $percentage,
                    'updated_at' => now(),
                ]);
        };

        static::saved($recalculate);
        static::deleted($recalculate);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ProgramRegistration::class, 'program_registration_id');
    }
}
