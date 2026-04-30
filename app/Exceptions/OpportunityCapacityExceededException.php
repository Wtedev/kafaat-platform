<?php

namespace App\Exceptions;

use RuntimeException;

class OpportunityCapacityExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This volunteer opportunity has reached its maximum capacity and cannot accept more registrations.');
    }
}
