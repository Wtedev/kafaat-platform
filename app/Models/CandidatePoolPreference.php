<?php

namespace App\Models;

use App\Enums\CandidatePoolPreferenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePoolPreference extends Model
{
    protected $fillable = [
        'user_id', 'current_status', 'current_consent_version_id',
        'prompted_at', 'decided_at', 'latest_event_id',
    ];

    protected function casts(): array
    {
        return [
            'current_status' => CandidatePoolPreferenceStatus::class,
            'prompted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentConsentVersion(): BelongsTo
    {
        return $this->belongsTo(CandidatePoolConsentVersion::class, 'current_consent_version_id');
    }
}
