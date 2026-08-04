<?php

namespace App\Models;

use App\Enums\SupportMessageSenderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'sender_type',
        'body',
        'is_system',
        'source',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'sender_type' => SupportMessageSenderType::class,
            'is_system' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isVisibleToBeneficiary(): bool
    {
        return $this->source !== 'legacy_admin_notes_marker'
            && $this->sender_type !== null;
    }

    public function scopeVisibleToBeneficiary($query)
    {
        return $query->where('source', '!=', 'legacy_admin_notes_marker');
    }

    public function scopeFromSupport($query)
    {
        return $query->whereIn('sender_type', [
            SupportMessageSenderType::Support->value,
            SupportMessageSenderType::System->value,
        ]);
    }

    public function scopeFromBeneficiary($query)
    {
        return $query->where('sender_type', SupportMessageSenderType::Beneficiary->value);
    }
}
