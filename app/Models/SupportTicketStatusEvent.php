<?php

namespace App\Models;

use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'support_ticket_id',
        'from_status',
        'to_status',
        'reason',
        'status_update_text',
        'actor_id',
        'support_ticket_message_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => SupportTicketStatus::class,
            'to_status' => SupportTicketStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }
}
