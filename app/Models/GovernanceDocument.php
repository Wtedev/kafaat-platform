<?php

namespace App\Models;

use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GovernanceDocument extends Model
{
    public const TYPES = [
        'organizational_structure'   => 'الهيكل التنظيمي',
        'investment_decisions'        => 'القرارات الاستثمارية',
        'general_assembly_minutes'    => 'محاضر اجتماعات الجمعية العمومية',
        'surveys'                     => 'استطلاعات',
        'executive_reports'           => 'التقارير التنفيذية',
        'financial_reports'           => 'التقارير المالية',
    ];

    protected $fillable = [
        'title',
        'description',
        'type',
        'file_path',
        'file_url',
        'cover_image',
        'document_date',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'sort_order'    => 'integer',
            'document_date' => 'date',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
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

    public function coverImageUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return PublicDiskPath::url($this->cover_image);
    }
}
