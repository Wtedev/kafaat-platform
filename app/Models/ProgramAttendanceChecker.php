<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class ProgramAttendanceChecker extends Model
{
    use Notifiable;

    protected $fillable = [
        'training_program_id',
        'name',
        'email',
        'invite_code_hash',
        'invite_code_expires_at',
        'invite_attempts',
        'verified_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'invite_code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
            'invite_attempts' => 'integer',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public function isInviteExpired(): bool
    {
        return $this->invite_code_expires_at === null
            || $this->invite_code_expires_at->isPast();
    }
}
