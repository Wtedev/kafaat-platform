<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
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
    ];

    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'status'        => AttendanceStatus::class,
        ];
    }

    // ─── Auto-sync attendance_percentage ─────────────────────────────────────

    /**
     * After any attendance record is saved or deleted, recalculate and persist
     * the attendance_percentage on the parent ProgramRegistration.
     *
     * Uses a direct DB update to avoid triggering Eloquent model events on
     * ProgramRegistration, which would risk infinite loops.
     */
    protected static function booted(): void
    {
        $recalculate = static function (self $record): void {
            $regId = $record->program_registration_id;

            $total = static::where('program_registration_id', $regId)->count();

            if ($total === 0) {
                return;
            }

            $present = static::where('program_registration_id', $regId)
                ->where('status', AttendanceStatus::Present->value)
                ->count();

            $percentage = round($present / $total * 100, 2);

            DB::table('program_registrations')
                ->where('id', $regId)
                ->update([
                    'attendance_percentage' => $percentage,
                    'updated_at'            => now(),
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
