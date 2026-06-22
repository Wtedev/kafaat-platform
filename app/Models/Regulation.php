<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Regulation extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'file_path',
        'file_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function filePublicUrl(): ?string
    {
        if ($this->file_url) {
            return $this->file_url;
        }

        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }
}
