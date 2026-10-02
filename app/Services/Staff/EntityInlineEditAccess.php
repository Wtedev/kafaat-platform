<?php

namespace App\Services\Staff;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class EntityInlineEditAccess
{
    public static function canUpdate(?User $user, Model $record): bool
    {
        return $user?->can('update', $record) ?? false;
    }
}
