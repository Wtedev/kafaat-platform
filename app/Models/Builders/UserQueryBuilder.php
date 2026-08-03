<?php

namespace App\Models\Builders;

use App\Exceptions\UserDeletionNotAllowedException;
use App\Models\User;
use App\Support\Auth\EmailNormalizer;
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

    /**
     * Case-insensitive email match (supports legacy mixed-case stored emails).
     */
    public function whereEmailIgnoreCase(string $email): self
    {
        return $this->whereRaw('lower(email) = ?', [EmailNormalizer::normalize($email)]);
    }
}
