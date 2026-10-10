<?php

namespace App\Models;

use App\Enums\AttendanceMarkSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAttendanceMark extends Model
{
    protected $fillable = [
        'program_attendance_link_id',
        'program_registration_id',
        'attended_at',
        'source',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
            'source' => AttendanceMarkSource::class,
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(ProgramAttendanceLink::class, 'program_attendance_link_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ProgramRegistration::class, 'program_registration_id');
    }

    public function riyadhLabel(): string
    {
        return $this->attended_at->timezone('Asia/Riyadh')->format('Y-m-d H:i:s');
    }
}
