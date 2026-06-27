<?php

namespace App\Models;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPolicy extends Model
{
    protected $fillable = [
        'resource_type',
        'name',
        'description',
        'trigger_event',
        'retention_period_days',
        'grace_period_days',
        'action',
        'enabled',
        'reason',
        'created_by',
        'updated_by',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_event' => RetentionTriggerEvent::class,
            'action' => RetentionPolicyAction::class,
            'enabled' => 'boolean',
            'retention_period_days' => 'integer',
            'grace_period_days' => 'integer',
            'effective_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
