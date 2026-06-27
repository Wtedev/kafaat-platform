<?php

namespace App\Models\Builders;

use App\Exceptions\UserDeletionNotAllowedException;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\App\Models\User>
 */
class UserQueryBuilder extends Builder
{
    public function delete($id = null): int
    {
        throw UserDeletionNotAllowedException::directDeletionBlocked();
    }
}
