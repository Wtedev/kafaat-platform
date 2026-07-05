<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceCommitteeMember extends Model
{
    protected $fillable = [
        'governance_committee_id',
        'name',
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

    public function committee(): BelongsTo
    {
        return $this->belongsTo(GovernanceCommittee::class, 'governance_committee_id');
    }
}
