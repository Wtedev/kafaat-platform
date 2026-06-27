<?php

namespace App\Models;

use App\Enums\PrivacyPolicyVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidatePoolConsentVersion extends Model
{
    protected $fillable = [
        'version', 'title', 'content', 'content_hash', 'status',
        'requires_reconsent', 'effective_at', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrivacyPolicyVersionStatus::class,
            'requires_reconsent' => 'boolean',
            'effective_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === PrivacyPolicyVersionStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === PrivacyPolicyVersionStatus::Active;
    }

    public function isDeletable(): bool
    {
        return $this->isDraft() && ! $this->events()->exists();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CandidatePoolConsentEvent::class);
    }
}
