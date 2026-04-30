<?php

namespace App\Exceptions;

use RuntimeException;

class ProgramCapacityExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This training program has reached its maximum capacity and cannot accept more registrations.');
    }
}
