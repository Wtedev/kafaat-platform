<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAlert extends Model
{
    protected $fillable = [
        'key', 'kind', 'title', 'body', 'count', 'payload',
        'changed_at', 'resolved_at', 'dismissed_at', 'dismissed_by', 'dismissed_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'count' => 'integer',
            'dismissed_count' => 'integer',
            'changed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<StaffAlert>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('resolved_at')->whereNull('dismissed_at');
    }

    public function dismisser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /** The group the alert belongs to, for its icon and who may see it. */
    public function group(): string
    {
        return explode('.', $this->kind)[0];
    }
}
