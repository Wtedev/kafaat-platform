<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Services\PathAttendanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PathAttendance extends Model
{
    protected $table = 'path_attendance';

    protected $fillable = [
        'path_registration_id',
        'attendance_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    protected static function booted(): void
    {
        $recalculate = static function (self $record): void {
            $regId = $record->path_registration_id;
            $registration = PathRegistration::query()
                ->with('learningPath.programs')
                ->find($regId);

            if ($registration === null) {
                return;
            }

            $percentage = app(PathAttendanceService::class)->calculatePercentage($registration);

            DB::table('path_registrations')
                ->where('id', $regId)
                ->update([
                    'attendance_percentage' => $percentage,
                    'updated_at' => now(),
                ]);
        };

        static::saved($recalculate);
        static::deleted($recalculate);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(PathRegistration::class, 'path_registration_id');
    }
}
