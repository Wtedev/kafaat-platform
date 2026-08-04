<?php

namespace App\Models;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_number',
        'name',
        'email',
        'subject',
        'category',
        'body',
        'page_url',
        'related_program_id',
        'status',
        'priority',
        'assigned_to',
        'admin_notes',
        'last_message_at',
        'last_message_sender_type',
        'closed_at',
        'resolution_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'category' => SupportTicketCategory::class,
            'priority' => SupportTicketPriority::class,
            'last_message_sender_type' => SupportMessageSenderType::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'related_program_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('id');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(SupportTicketStatusEvent::class)->orderBy('id');
    }

    public function readCursors(): HasMany
    {
        return $this->hasMany(SupportTicketReadCursor::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)->latestOfMany('id');
    }

    public function displayNumber(): string
    {
        return $this->ticket_number ?: ('#'.$this->getKey());
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOpenish(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            SupportTicketStatus::Closed->value,
            SupportTicketStatus::Resolved->value,
        ]);
    }
}
