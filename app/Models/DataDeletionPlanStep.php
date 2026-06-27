<?php

namespace App\Models;

use App\Enums\DataDeletionPlanStepStatus;
use App\Enums\DeletionHandlerName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataDeletionPlanStep extends Model
{
    protected $fillable = [
        'data_deletion_plan_id',
        'handler',
        'status',
        'started_at',
        'completed_at',
        'attempts',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'handler' => DeletionHandlerName::class,
            'status' => DataDeletionPlanStepStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DataDeletionPlan::class, 'data_deletion_plan_id');
    }
}
