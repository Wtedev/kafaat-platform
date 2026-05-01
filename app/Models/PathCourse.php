<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PathCourse extends Model
{
    protected $fillable = [
        'learning_path_id',
        'title',
        'description',
        'content',
        'video_url',
        'duration_minutes',
        'is_required',
        'sort_order',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'duration_minutes' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::Published);
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', CourseStatus::Draft);
    }

    public function scopeRequired(Builder $query): void
    {
        $query->where('is_required', true);
    }

    public function scopeAccessible(Builder $query): void
    {
        $query->where('status', CourseStatus::Published);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserCourseProgress::class);
    }
}
