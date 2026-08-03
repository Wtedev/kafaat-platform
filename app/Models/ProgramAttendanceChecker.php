<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAttendanceChecker extends Model
{
    protected $fillable = [
        'training_program_id',
        'name',
        'email',
        'invite_code_hash',
        'invite_code_expires_at',
        'invite_attempts',
        'verified_at',
        'access_token_hash',
        'access_version',
        'last_used_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'invite_code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_active' => 'boolean',
            'invite_attempts' => 'integer',
            'access_version' => 'integer',
        ];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function hasAccessLink(): bool
    {
        return filled($this->access_token_hash);
    }

    public function isInviteExpired(): bool
    {
        return $this->invite_code_expires_at === null
            || $this->invite_code_expires_at->isPast();
    }
}
