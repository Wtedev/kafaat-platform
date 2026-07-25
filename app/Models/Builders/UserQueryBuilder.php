<?php

namespace App\Models\Builders;

use App\Exceptions\UserDeletionNotAllowedException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<User>
 */
class UserQueryBuilder extends Builder
{
    public function delete($id = null): int
    {
        throw UserDeletionNotAllowedException::directDeletionBlocked();
    }
}
