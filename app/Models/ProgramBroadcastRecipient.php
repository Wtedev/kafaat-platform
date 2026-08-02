<?php

namespace App\Models;

use App\Enums\ProgramBroadcastRecipientStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramBroadcastRecipient extends Model
{
    protected $fillable = [
        'program_broadcast_id',
        'user_id',
        'program_registration_id',
        'email',
        'name',
        'registration_status',
        'status',
        'provider_message_id',
        'failure_reason',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'registration_status' => RegistrationStatus::class,
            'status' => ProgramBroadcastRecipientStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(ProgramBroadcast::class, 'program_broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programRegistration(): BelongsTo
    {
        return $this->belongsTo(ProgramRegistration::class);
    }

    public function isPending(): bool
    {
        return $this->status === ProgramBroadcastRecipientStatus::Pending;
    }

    public function isFailed(): bool
    {
        return $this->status === ProgramBroadcastRecipientStatus::Failed;
    }
}
