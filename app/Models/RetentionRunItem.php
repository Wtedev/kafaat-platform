<?php

namespace App\Models;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionRunItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionRunItem extends Model
{
    protected $fillable = [
        'retention_run_id',
        'resource_type',
        'resource_identifier',
        'source_id',
        'action',
        'status',
        'attempts',
        'failure_code',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => RetentionPolicyAction::class,
            'status' => RetentionRunItemStatus::class,
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(RetentionRun::class, 'retention_run_id');
    }
}
