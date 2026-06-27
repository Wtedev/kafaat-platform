<?php

namespace App\Models;

use App\Enums\PrivacyPolicyAcknowledgementSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyPolicyAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'privacy_policy_version_id',
        'acknowledgement_text_snapshot',
        'policy_content_hash',
        'acknowledged_at',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'source' => PrivacyPolicyAcknowledgementSource::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function privacyPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(PrivacyPolicyVersion::class);
    }
}
