<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * موظفة/موظف يدير طلبات بيوت الخبرة من الواجهة المبسطة (دخول برابط عبر البريد، بدون كلمة مرور).
 */
class ExperienceHouseManager extends Model
{
    protected $fillable = ['name', 'email', 'is_active', 'invited_by_id', 'activated_at', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => mb_strtolower(trim((string) $value)),
        );
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by_id');
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?: $this->name;
    }

    public function initial(): string
    {
        return mb_substr(trim($this->name), 0, 1);
    }
}
