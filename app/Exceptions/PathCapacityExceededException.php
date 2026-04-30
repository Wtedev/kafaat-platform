<?php

namespace App\Exceptions;

use RuntimeException;

class PathCapacityExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This learning path has reached its maximum capacity and cannot accept more registrations.');
    }
}
