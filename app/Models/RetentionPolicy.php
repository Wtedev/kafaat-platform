<?php

namespace App\Models;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionPolicyStatus;
use App\Enums\RetentionTriggerEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RetentionPolicy extends Model
{
    protected $fillable = [
        'uuid',
        'resource_type',
        'name',
        'description',
        'trigger_type',
        'retention_period_days',
        'grace_period_days',
        'action',
        'status',
        'reason',
        'created_by',
        'updated_by',
        'activated_by',
        'effective_at',
        'activated_at',
        'last_previewed_at',
        'last_preview_count',
        'requires_manual_approval',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => RetentionTriggerEvent::class,
            'action' => RetentionPolicyAction::class,
            'status' => RetentionPolicyStatus::class,
            'retention_period_days' => 'integer',
            'grace_period_days' => 'integer',
            'last_preview_count' => 'integer',
            'requires_manual_approval' => 'boolean',
            'effective_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_previewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RetentionPolicy $policy): void {
            if (blank($policy->uuid)) {
                $policy->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(RetentionRun::class);
    }

    public function isActive(): bool
    {
        return $this->status === RetentionPolicyStatus::Active;
    }

    public function isEditable(): bool
    {
        return $this->status === RetentionPolicyStatus::Draft;
    }

    public function hasApprovedRetentionPeriod(): bool
    {
        return $this->retention_period_days !== null;
    }

    public function isEffectiveAt(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return $this->effective_at === null || $this->effective_at->lte($at);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
