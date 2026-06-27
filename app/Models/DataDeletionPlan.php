<?php

namespace App\Models;

use App\Enums\DataDeletionPlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataDeletionPlan extends Model
{
    protected $fillable = [
        'uuid',
        'privacy_request_id',
        'user_id',
        'status',
        'plan_snapshot',
        'approved_by',
        'approved_at',
        'execution_started_at',
        'execution_completed_at',
        'failure_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => DataDeletionPlanStatus::class,
            'plan_snapshot' => 'array',
            'approved_at' => 'datetime',
            'execution_started_at' => 'datetime',
            'execution_completed_at' => 'datetime',
        ];
    }

    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DataDeletionPlanStep::class)->orderBy('id');
    }
}
