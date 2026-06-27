<?php

namespace App\Models;

use App\Enums\CandidatePoolConsentEventType;
use App\Enums\CandidatePoolConsentSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePoolConsentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'candidate_pool_consent_version_id', 'event_type',
        'consent_text_snapshot', 'consent_content_hash', 'source',
        'ip_address', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CandidatePoolConsentEventType::class,
            'source' => CandidatePoolConsentSource::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consentVersion(): BelongsTo
    {
        return $this->belongsTo(CandidatePoolConsentVersion::class, 'candidate_pool_consent_version_id');
    }
}
