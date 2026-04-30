<?php

namespace App\Exceptions;

use RuntimeException;

class RegistrationWindowClosedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Registration for this training program is currently closed.');
    }
}
