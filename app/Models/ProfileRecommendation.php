<?php

namespace App\Models;

// TODO: Optional Filament resource — admins can CRUD recommendations per beneficiary (user_id).

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'author_name',
        'author_title',
        'body',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
