<?php

namespace App\Exceptions;

use RuntimeException;

class ProgramBelongsToLearningPathException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This training program is part of a learning path; registration is only via the path.');
    }
}
