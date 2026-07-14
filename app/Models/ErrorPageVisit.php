<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorPageVisit extends Model
{
    protected $fillable = [
        'status_code',
        'requested_url',
        'route_name',
        'request_method',
        'ip_address',
        'user_agent',
        'referer',
        'user_id',
        'exception_class',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
