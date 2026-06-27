<?php

namespace App\Support\Privacy;

use App\Exceptions\UserDeletionNotAllowedException;

final class UserDeletionGuard
{
    private static int $authorizationDepth = 0;

    public static function isAuthorized(): bool
    {
        return self::$authorizationDepth > 0;
    }

    /**
     * @internal Demo seeders only.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runAuthorized(callable $callback): mixed
    {
        self::$authorizationDepth++;

        try {
            return $callback();
        } finally {
            self::$authorizationDepth--;
        }
    }

    public static function assertAuthorized(): void
    {
        if (! self::isAuthorized()) {
            throw UserDeletionNotAllowedException::directDeletionBlocked();
        }
    }
}
