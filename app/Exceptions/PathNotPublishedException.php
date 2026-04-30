<?php

namespace App\Exceptions;

use RuntimeException;

class PathNotPublishedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This learning path is not currently available for registration.');
    }
}
