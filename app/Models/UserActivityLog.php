<?php

namespace App\Models;

use App\Enums\UserActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'title',
        'detail',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => UserActivityAction::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
