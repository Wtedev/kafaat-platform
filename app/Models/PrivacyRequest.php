<?php

namespace App\Models;

use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrivacyRequest extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'request_type',
        'status',
        'request_details',
        'identity_verification_method',
        'identity_verified_at',
        'assigned_to',
        'due_at',
        'decision_summary',
        'rejection_reason_code',
        'rejection_reason',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'request_type' => PrivacyRequestType::class,
            'status' => PrivacyRequestStatus::class,
            'request_details' => 'array',
            'identity_verified_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PrivacyRequestEvent::class)->orderBy('occurred_at');
    }

    public function deletionPlan(): HasOne
    {
        return $this->hasOne(DataDeletionPlan::class);
    }
}
