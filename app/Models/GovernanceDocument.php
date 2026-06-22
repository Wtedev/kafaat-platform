<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
            return $this->file_url;
        }

        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }

    public function coverImageUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_image);
    }
}
