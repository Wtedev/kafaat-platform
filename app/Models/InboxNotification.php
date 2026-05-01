<?php

namespace App\Models;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxNotification extends Model
{
    protected $table = 'in_app_notifications';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'sender_id',
        'target_type',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InboxNotificationType::class,
            'target_type' => NotificationTargetType::class,
            'read_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function markAsRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }
}
