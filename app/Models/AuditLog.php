<?php

namespace App\Models;

use App\Enums\AuditLogResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_type',
        'action',
        'target_user_id',
        'resource_type',
        'resource_id',
        'result',
        'reason',
        'request_id',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => AuditLogResult::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
