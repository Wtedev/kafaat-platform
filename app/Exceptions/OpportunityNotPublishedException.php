<?php

namespace App\Exceptions;

use RuntimeException;

class OpportunityNotPublishedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This volunteer opportunity is not currently open for registration.');
    }
}
