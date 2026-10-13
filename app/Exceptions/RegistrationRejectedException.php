<?php

namespace App\Exceptions;

use RuntimeException;

class RegistrationRejectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Your previous registration for this volunteer opportunity was rejected, so you cannot register again.');
    }
}