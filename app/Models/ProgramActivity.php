<?php

namespace App\Models;

use App\Enums\ProgramActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in a training program's activity log. Append-only.
 */
class ProgramActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'training_program_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ProgramActivityAction::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
