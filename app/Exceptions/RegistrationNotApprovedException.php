<?php

namespace App\Exceptions;

use RuntimeException;

class RegistrationNotApprovedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Only approved registrations can be marked as completed.');
    }
}
