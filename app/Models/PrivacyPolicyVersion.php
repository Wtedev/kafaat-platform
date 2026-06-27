<?php

namespace App\Models;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\PrivacyPolicyVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivacyPolicyVersion extends Model
{
    /** @use HasFactory<PrivacyPolicyVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'version',
        'title',
        'content',
        'content_hash',
        'effective_at',
        'published_at',
        'status',
        'requires_reacknowledgement',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'published_at' => 'datetime',
            'status' => PrivacyPolicyVersionStatus::class,
            'requires_reacknowledgement' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PrivacyPolicyVersionStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PrivacyPolicyVersionStatus::Draft);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublishedPublic(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PrivacyPolicyVersionStatus::Active,
            PrivacyPolicyVersionStatus::Archived,
        ])->whereNotNull('published_at');
    }

    public function isDraft(): bool
    {
        return $this->status === PrivacyPolicyVersionStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === PrivacyPolicyVersionStatus::Active;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function isDeletable(): bool
    {
        return $this->isDraft() && ! $this->acknowledgements()->exists();
    }

    public function recomputeContentHash(): string
    {
        return PrivacyPolicyContentHasher::hash((string) $this->content);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PrivacyPolicyAcknowledgement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
