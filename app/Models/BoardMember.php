<?php

namespace App\Models;

use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BoardMember extends Model
{
    public const GROUP_BOARD = 'board';

    public const GROUP_GENERAL_ASSEMBLY = 'general_assembly';

    public const GROUPS = [
        self::GROUP_BOARD => 'أعضاء مجلس الإدارة',
        self::GROUP_GENERAL_ASSEMBLY => 'أعضاء الجمعية العمومية',
    ];

    protected $fillable = [
        'group',
        'name',
        'role',
        'bio',
        'photo',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'group' => self::GROUP_BOARD,
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

    public function scopeBoard(Builder $query): void
    {
        $query->where('group', self::GROUP_BOARD);
    }

    public function scopeGeneralAssembly(Builder $query): void
    {
        $query->where('group', self::GROUP_GENERAL_ASSEMBLY);
    }

    public function photoPublicUrl(): ?string
    {
        return PublicDiskPath::url($this->photo);
    }
}
