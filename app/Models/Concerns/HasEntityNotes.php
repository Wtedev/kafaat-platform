<?php

namespace App\Models\Concerns;

use App\Models\EntityNote;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEntityNotes
{
    /**
     * @return MorphMany<EntityNote, $this>
     */
    public function entityNotes(): MorphMany
    {
        return $this->morphMany(EntityNote::class, 'noteable');
    }
}
