<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateRegistrationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You already have an active registration for this learning path.');
    }
}
