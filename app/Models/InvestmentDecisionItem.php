<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentDecisionItem extends Model
{
    protected $fillable = [
        'investment_decision_year_id',
        'content',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(InvestmentDecisionYear::class, 'investment_decision_year_id');
    }
}
