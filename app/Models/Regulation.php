<?php

namespace App\Models;

use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            // مخطّطات آمنة فقط لمنع روابط javascript:/data: في href.
            return preg_match('#^https?://#i', (string) $this->file_url) === 1
                ? $this->file_url
                : null;
        }

        if ($this->file_path) {
            return PublicDiskPath::url($this->file_path);
        }

        return null;
    }
}
