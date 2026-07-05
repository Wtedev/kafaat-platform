<?php

namespace App\Models;

use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentDecisionYear extends Model
{
    protected $fillable = [
        'year',
        'title',
        'file_path',
        'empty_message',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvestmentDecisionItem::class)->orderBy('sort_order');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function filePublicUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return PublicDiskPath::url($this->file_path);
    }

    public function hasPublishedContent(): bool
    {
        return $this->activeItems()->exists() || filled($this->empty_message);
    }
}
