<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCourseProgress extends Model
{
    protected $table = 'user_course_progress';

    protected $fillable = [
        'user_id',
        'path_course_id',
        'progress_percentage',
        'score',
        'status',
        'completed_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProgressStatus::class,
            'progress_percentage' => 'decimal:2',
            'score' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', ProgressStatus::Completed);
    }

    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', ProgressStatus::InProgress);
    }

    public function scopeNotStarted(Builder $query): void
    {
        $query->where('status', ProgressStatus::NotStarted);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pathCourse(): BelongsTo
    {
        return $this->belongsTo(PathCourse::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
