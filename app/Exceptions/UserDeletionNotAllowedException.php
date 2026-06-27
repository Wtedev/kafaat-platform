<?php

namespace App\Exceptions;

use RuntimeException;

class UserDeletionNotAllowedException extends RuntimeException
{
    public static function directDeletionBlocked(): self
    {
        return new self(
            'User deletion is not allowed outside the approved account deletion workflow.',
        );
    }

    public static function maintenanceDeletionBlocked(string $reason): self
    {
        return new self($reason);
    }
}
